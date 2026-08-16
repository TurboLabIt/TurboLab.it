<?php
namespace App\Tests\Editor;

use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Tests\BaseT;


/**
 * Code blocks: the editor "Codice" button wraps code in <pre><code>, allowed in EVERY article (no
 * allowExtendedHtml needed). A code block is byte-significant, so the save-time text fixers must
 * leave it alone: no double-space collapse (spaces ARE the indentation), no accent fixes (velocita
 * may be an identifier, E'...' a PostgreSQL string), no legacy prose repairs (a block opening with
 * >> must not become »). Fine typography stays normalized even in code, on purpose: curly quotes
 * and non-breaking spaces there are paste damage, and a command must still work when copied.
 *
 * The one thing that must NEVER skip <pre> is HTMLPurifier itself.
 *
 * @see \App\Service\HtmlProcessorBase::applyOutsidePreBlocks()
 * @see \App\Service\HtmlProcessorForStorage::ALLOWED_TAGS
 * @see \App\Service\TextProcessor
 */
class CodeBlockStorageTest extends BaseT
{
    public function testCodeBlockSurvivesStorageInOrdinaryArticles() : void
    {
        // what CKEditor actually POSTs: single configured language, class emitted empty server-side anyway
        $body =
            '<p>Ecco i comandi:</p>' .
            '<pre><code class="language-plaintext">sudo apt update' . "\n\n" . 'sudo apt upgrade</code></pre>' .
            '<p>Fine della guida.</p>';

        $stored = $this->processBody($body);

        $this->assertStringContainsString(
            '<pre><code>sudo apt update' . "\n\n" . 'sudo apt upgrade</code></pre>', $stored,
            'A plain article lost its code block: <pre> must be in the base allowlist, newlines included'
        );
        $this->assertStringNotContainsString('language-plaintext', $stored);
        $this->assertStringContainsString('<p>Ecco i comandi:</p>', $stored);
        $this->assertStringContainsString('<p>Fine della guida.</p>', $stored);
    }


    public function testIndentationSurvivesInsideCodeBlocksWhileProseIsStillCollapsed() : void
    {
        $body =
            '<p>Testo  con  doppi  spazi</p>' .
            '<pre><code>if ($a) {' . "\n" . '    return  1;' . "\n" . '}</code></pre>';

        $stored = $this->processBody($body);

        $this->assertStringContainsString('<p>Testo con doppi spazi</p>', $stored);
        $this->assertStringContainsString(
            '<pre><code>if ($a) {' . "\n" . '    return  1;' . "\n" . '}</code></pre>', $stored,
            'The double-space collapse ran inside a code block: the indentation is gone'
        );
    }


    public function testAccentFixersAndLegacyRepairsLeaveCodeBlocksAlone() : void
    {
        $body =
            "<p>E' vero: perche' non funziona su internet</p>" .
            '<p>&gt;&gt; testo citato</p>' .
            '<pre><code>' .
                "SELECT E' ' || citta FROM tabella" . "\n" .
                '$velocita = 5  # perche' . "'" . "\n" .
                '&gt;&gt; append.log' .
            '</code></pre>';

        $stored = $this->processBody($body);

        // prose still gets the full treatment...
        $this->assertStringContainsString('<p>È vero: perché non funziona su Internet</p>', $stored);
        $this->assertStringContainsString('<p>» testo citato</p>', $stored);

        // ...the code block gets none of it
        $this->assertStringContainsString(
            '<pre><code>' .
                "SELECT E' ' || citta FROM tabella" . "\n" .
                '$velocita = 5  # perche' . "'" . "\n" .
                '&gt;&gt; append.log' .
            '</code></pre>',
            $stored,
            'A text fixer rewrote the inside of a code block: accents, E\', >> and spacing must stay as typed'
        );
    }


    public function testFineTypographyIsStillNormalizedInsideCodeBlocks() : void
    {
        $body = '<pre><code>git clone “https://example.com/repo.git”' . "\n" . "cd\u{00A0}\u{00A0}repo</code></pre>";

        $stored = $this->processBody($body);

        // curly quotes → straight, nbsp → real space: paste damage repaired even in code…
        // …but the two resulting spaces survive: the collapse does skip <pre>
        $this->assertSame(
            '<pre><code>git clone "https://example.com/repo.git"' . "\n" . 'cd  repo</code></pre>',
            $stored
        );
    }


    /**
     * A code block may declare its language (class="language-*"), which drives the client-side
     * highlighting. Attr.AllowedClasses caps the values to ALLOWED_CODE_LANGUAGES — and class stays
     * a code-only attribute: the door opened for language-* must not let class through anywhere else.
     */
    public function testAllowedLanguageClassesSurviveAndAnythingElseIsStripped() : void
    {
        $stored = $this->processBody(
            '<pre><code class="language-bash">echo "ciao"</code></pre>' .
            '<pre><code class="language-ruby">puts :no</code></pre>' .
            '<pre><code class="language-bash hljs evil">ls</code></pre>' .
            '<p class="language-bash">prosa</p>'
        );

        $this->assertStringContainsString(
            '<pre><code class="language-bash">echo "ciao"</code></pre>', $stored,
            'A whitelisted language class was stripped: the language choice is lost on save'
        );

        // a language the server does not know: the class goes, the code stays
        $this->assertStringNotContainsString('language-ruby', $stored);
        $this->assertStringContainsString('<pre><code>puts :no</code></pre>', $stored);

        // foreign classes are filtered out even when sitting next to a valid one
        $this->assertStringContainsString('<pre><code class="language-bash">ls</code></pre>', $stored);
        $this->assertStringNotContainsString('hljs', $stored);
        $this->assertStringNotContainsString('evil', $stored);

        // class remains code-only
        $this->assertStringContainsString('<p>prosa</p>', $stored);
    }


    public function testCodeBlockContentIsStillPurified() : void
    {
        $body = '<pre onclick="alert(1)"><code><script>alert("xss")</script>testo pulito</code></pre>';

        $stored = $this->processBody($body);

        $this->assertStringNotContainsString(
            '<script', $stored,
            'The <pre> fixer exemption must never extend to HTMLPurifier: sanitization saw a hole'
        );
        $this->assertStringNotContainsString('alert', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringContainsString('testo pulito', $stored);
    }


    /**
     * Editing an article re-runs the whole pipeline on what the previous save stored: a code block
     * must come out byte-identical, or every touch-and-save would corrupt it a bit more (the HTML5
     * parser/serializer newline quirks around <pre> are the classic way this breaks).
     */
    public function testStoredCodeBlockIsStableAcrossResaves() : void
    {
        $body =
            '<p>Prosa iniziale.</p>' .
            '<pre><code>' . "\n" . 'prima riga dopo una vuota' . "\n" . '    indentata</code></pre>' .
            '<pre><code class="language-powershell">Get-ChildItem -Path C:\</code></pre>';

        $storedOnce  = $this->processBody($body);
        $storedTwice = $this->processBody($storedOnce);

        $this->assertSame($storedOnce, $storedTwice);
        $this->assertStringContainsString('<pre><code>', $storedOnce);
    }


    /** The abstract feeds search results and social previews: it must come from prose, never from code. */
    public function testTheAbstractIsNeverExtractedFromACodeBlock() : void
    {
        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle('Code block abstract probe ' . bin2hex(random_bytes(4)))
            ->setBody('<pre><code>sudo rm -rf bin/</code></pre><p>La spiegazione vera del comando.</p>');

        $this->assertSame('La spiegazione vera del comando.', $editor->getEntity()->getAbstract());
    }


    /**
     * Nothing is persisted: setBody() only rewrites the in-memory entity, save() is never called.
     * allowExtendedHtml is deliberately NOT set: code blocks belong to the base allowlist.
     */
    private function processBody(string $rawBody) : string
    {
        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();

        $editor
            ->setTitle('Code block probe ' . bin2hex(random_bytes(4)))
            ->setBody($rawBody);

        return (string)$editor->getBody();
    }
}
