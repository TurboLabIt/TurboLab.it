<?php
namespace App\Tests\Security;

use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Anti-regression guard for docs/security-audit.md finding #26
 * ("XSS stored: la regex `alt` riscrive l'HTML dopo HTMLPurifier") — now RESOLVED.
 *
 * Background: TextProcessor::processRawInputBodyForStorage() used to run purify() and THEN a
 * preg_replace('/\s*alt\s*=\s*([\'"]).*?\1/i', ...) (HtmlProcessorForStorage::removeAltAttribute)
 * that rewrote already-sanitized HTML. A crafted body could make the non-greedy match start inside
 * an href value and end in a later text node, deleting across the tag boundary and corrupting the
 * stored article. The active PoC showed no code execution reproduced on the current stack (libxml
 * 2.15 drops the broken tag instead of promoting its text), but it did silently destroy author
 * content — so the regex was removed outright. It is safe to drop with no replacement because
 * processArticleBody() rebuilds every <img> as a placeholder without an alt (HTMLPurifier only ever
 * emits an empty alt="" on images); a full differential over all 4763 articles produced byte-
 * identical stored output with and without the regex.
 *
 * These tests lock that in:
 *
 *  1. testCraftedBodyNeverInjectsExecutableVector() — the #26 payloads must never yield an on*
 *     handler, a <script>, or a javascript: URL, in either the stored body or the display output.
 *     Fails loudly if a future change ever turns the leftover text into executable markup.
 *
 *  2. testAuthorContentWithLiteralAltIsPreserved() — the fix must NOT reintroduce a regex/textual
 *     alt-stripping step: an author legitimately writing `alt="..."` in body text or inside a link
 *     must keep it verbatim (the old regex silently deleted it).
 *
 *  3. testImageAltHandlingIsUnchanged() — images must still be stored as a bare placeholder with no
 *     alt attribute, exactly as before the fix.
 */
class ArticleBodyAltRegexXssTest extends BaseT
{
    // alt=' opens inside the href query string (the single quote survives HTMLPurifier's ENT_COMPAT
    // attribute encoding); the matching ' lands in a later text node. This is finding #26's
    // canonical payload.
    private const string CANONICAL_PAYLOAD =
        '<p>Leggi <a href="https://esempio.example/?q=alt=\'X">la guida</a>' .
        ' qui\' onmouseover=alert(document.domain) x=\'</p>';


    protected static function buildEditor() : ArticleEditor
    {
        static::loginAsSystem();
        return static::getService(Factory::class)->createArticleEditor();
    }


    public static function craftedBodyProvider() : array
    {
        return static::repackDataProviderArray([
            'href-single-quote-close-in-text' => self::CANONICAL_PAYLOAD,

            'alt-in-text-before-anchor' =>
                '<p>alt=\'<a href="https://esempio.example/">L</a>\' onmouseover=alert(1) x=\'</p>',

            'href-close-then-following-tag' =>
                '<p><a href="https://esempio.example/?q=alt=\'">L</a>\' onmouseover=alert(1) <strong>s</strong></p>',

            'cross-paragraph-boundary' =>
                '<p>k alt=\'v</p><p>w\' onmouseover=alert(1) x=\'</p>',

            'youtube-iframe-src' =>
                '<p><iframe src="https://www.youtube.com/embed/ABCDEFGHIJK?x=alt=\'"></iframe>' .
                ' t\' onmouseover=alert(1) <b>c</b></p>',
        ]);
    }


    #[DataProvider('craftedBodyProvider')]
    public function testCraftedBodyNeverInjectsExecutableVector(string $craftedBody) : void
    {
        $editor = static::buildEditor()->setBody($craftedBody);

        // as persisted in the DB (the article body is later emitted with |raw)
        $this->assertNoExecutableVector($editor->getEntity()->getBody(), 'stored body');

        // as rendered on the article page
        $this->assertNoExecutableVector($editor->getBodyForDisplay(), 'display body');
    }


    public function testAuthorContentWithLiteralAltIsPreserved() : void
    {
        // 1) The canonical #26 payload is now stored intact (link + text kept); the old regex used
        //    to destroy everything from the first alt=' onwards, leaving only "<p>Leggi </p>".
        $stored = static::buildEditor()->setBody(self::CANONICAL_PAYLOAD)->getEntity()->getBody();
        $this->assertStringContainsString('la guida', $stored, 'The author link text must survive.');
        $this->assertStringContainsString('esempio.example', $stored, 'The author link href must survive.');
        $this->assertNoExecutableVector($stored, 'stored body'); // ...but still no executable markup

        // 2) An article that legitimately talks about the alt attribute keeps the literal text.
        $stored = static::buildEditor()
            ->setBody('<p>Per il testo alternativo usa l\'attributo alt="descrizione immagine".</p>')
            ->getEntity()->getBody();
        $this->assertStringContainsString('alt="descrizione immagine"', $stored,
            'Literal alt="..." in body text must not be deleted.');

        // 3) A single quote plus alt= inside a real href is no longer truncated.
        $stored = static::buildEditor()
            ->setBody('<p><a href="https://esempio.example/?ref=alt=\'X\'">link</a></p>')
            ->getEntity()->getBody();
        $this->assertStringContainsString('?ref=alt=\'X\'', $stored,
            'An href containing alt=\'...\' must not be truncated.');
    }


    public function testImageAltHandlingIsUnchanged() : void
    {
        // Images are stored as a bare placeholder carrying only the image id — never an alt.
        // (The meaningful alt is added later on the display path.)
        $stored = static::buildEditor()
            ->setBody('<p><img src="https://dev0.turbolab.it/immagini/reg/foo-22106.jpg" alt="Immagine 1 Qualcosa"></p>')
            ->getEntity()->getBody();

        $this->assertStringContainsString('==###immagine::id::22106###==', $stored);
        $this->assertStringNotContainsString('alt', $stored, 'The stored placeholder image must carry no alt.');
    }


    /**
     * Fails if the given HTML contains any script-execution vector: an on* event-handler attribute,
     * a <script> element, or a javascript: URL. Parsed with the same libxml round-trip the app uses.
     */
    protected function assertNoExecutableVector(string $html, string $where) : void
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="tli-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//@*') as $attr) {
            $this->assertDoesNotMatchRegularExpression(
                '/^on/i', $attr->nodeName,
                "Injected event-handler attribute '{$attr->nodeName}' found in {$where}: {$html}"
            );
        }

        $this->assertSame(
            0, $xpath->query('//script')->length,
            "Injected <script> element found in {$where}: {$html}"
        );

        foreach ($xpath->query('//@href | //@src | //@srcdoc') as $attr) {
            $this->assertStringNotContainsStringIgnoringCase(
                'javascript:', $attr->nodeValue,
                "Injected javascript: URL found in {$where}: {$html}"
            );
        }
    }
}
