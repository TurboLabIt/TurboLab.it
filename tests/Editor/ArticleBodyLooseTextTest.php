<?php
namespace App\Tests\Editor;

use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Text outside any element ("loose" text), which the bodies imported from TLI1 have plenty of:
 * `<p></p>⏎Cercando in giro per il Web:⏎<ul>…`. Since libxml 2.14 (its rewritten HTML5 parser, on the box from
 * June 2026) the parse dropped it: the article pages hid it, and the editor, loaded with the same HTML, never got
 * it, so the next save deleted it for good. On 2026-11-18, 560 published articles hid about 200,000 words this way.
 * parseHTML() now gives such text a parent while parsing, and puts it in a <p>, as the old parser did.
 *
 * The bodies are set on the entity directly, as the database holds them: setBody() would purify them first, and
 * the purifier already wraps loose text on its own (AutoFormat.AutoParagraph).
 *
 * @see \App\Service\HtmlProcessorBase::parseHTML()
 * @see \App\Service\HtmlProcessorBase::implyParagraphs()
 */
class ArticleBodyLooseTextTest extends BaseT
{
    public static function looseTextProvider() : array
    {
        return [
            'between two paragraphs'    => ['<p>uno</p>testo libero<p>due</p>',     '<p>uno</p><p>testo libero</p><p>due</p>'],
            'before the first element'  => ['testo iniziale<p>uno</p>',             '<p>testo iniziale</p><p>uno</p>'],
            'after the last element'    => ['<p>uno</p>testo finale',               '<p>uno</p><p>testo finale</p>'],
            'nothing but text'          => ['solo testo',                           '<p>solo testo</p>'],

            // the inline elements next to the text go in the same paragraph, the spaces between them too
            'bold before the text'      => ['<p>uno</p><strong>grassetto</strong> e coda<p>due</p>', '<p>uno</p><p><strong>grassetto</strong> e coda</p><p>due</p>'],
            'two inline elements'       => ['<p>uno</p><code>Image Analysis</code> <strong>consente</strong><p>due</p>', '<p>uno</p><p><code>Image Analysis</code> <strong>consente</strong></p><p>due</p>'],

            // as TLI1 stored it (article 4343): newlines around the text, a list right after
            'TLI1 body'                 => [
                "<p>uno</p><p></p>\nCercando in giro per il Web:\n\n<ul>\n\t<li>voce</li>\n</ul>",
                "<p>uno</p><p></p><p>Cercando in giro per il Web:</p><ul>\n\t<li>voce</li>\n</ul>"
            ],
            'accents and emoji'         => ['<p>uno</p>perché è così 🙂<p>due</p>',  '<p>uno</p><p>perché è così 🙂</p><p>due</p>'],

            // closing tags with nothing to close don't end the parse early
            'a stray </div>'            => ['<p>uno</p></div>dopo<p>due</p>',       '<p>uno</p><p>dopo</p><p>due</p>'],
            'a stray </body></html>'    => ['<p>uno</p></body></html>dopo<p>due</p>', '<p>uno</p><p>dopo</p><p>due</p>'],

            // ...and what holds no loose text comes out as it always did
            'whitespace between blocks' => ["<p>uno</p>\n\n<p>due</p>\n",           '<p>uno</p><p>due</p>'],
            'an image between blocks'   => ['<p>uno</p><img src="x.png"><p>due</p>', '<p>uno</p><img src="x.png"><p>due</p>'],
            'a comment'                 => ['<!-- nota --><p>uno</p>',              '<!-- nota --><p>uno</p>'],
            'text inside a list'        => ['<ul><li>uno</li><li>due</li></ul>',    '<ul><li>uno</li><li>due</li></ul>'],
        ];
    }


    #[DataProvider('looseTextProvider')]
    public function testLooseTextIsShown(string $storedBody, string $expected) : void
    {
        $this->assertSame($expected, $this->createArticleStoredAs($storedBody)->getBodyForDisplay());
    }


    /**
     * The editor is loaded with the display HTML, and its save posts it back: every word of the stored body must
     * come through the round trip, now as paragraphs.
     */
    public function testLooseTextSurvivesASave() : void
    {
        $storedBody =
            "<p>Introduzione.</p><p></p>\nAnche come strumento di backup dati, il programma\n" .
            "<strong>funziona bene</strong> e senza costi.\n<h2>Download</h2>\nIl file pesa 3 MB.";

        $displayed = $this->createArticleStoredAs($storedBody)->getBodyForDisplay();

        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle('Loose text probe ' . bin2hex(random_bytes(4)))
            ->setBody($displayed);

        // the empty <p></p> goes (AutoFormat.RemoveEmpty), the newline inside the text stays as it was stored
        $this->assertSame(
            "<p>Introduzione.</p><p>Anche come strumento di backup dati, il programma\n<strong>funziona bene</strong> " .
            'e senza costi.</p><h2>Download</h2><p>Il file pesa 3 MB.</p>',
            $editor->getBody()
        );
    }


    protected function createArticleStoredAs(string $body) : ArticleEditor
    {
        /** @var ArticleEditor $article */
        $article = static::getService(Factory::class)->createArticleEditor();
        $article->getEntity()->setBody($body);
        return $article;
    }
}
