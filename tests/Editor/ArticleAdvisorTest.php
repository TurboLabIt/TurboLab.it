<?php
namespace App\Tests\Editor;

use App\Service\Cms\ArticleAdvisor;
use App\Service\Factory;
use App\Tests\BaseT;


/**
 * The "Verifica" toolbar button (app_editor_article_advise) runs ArticleAdvisor over the saved
 * article and reports soft warnings. First check: leftover references to Google Docs
 * ("docs.google.com/document/"), whether visible as text or hidden in a link's href — the classic
 * residue of drafting an article in a shared document. Each occurrence must come with an excerpt
 * the author can use to locate it, and an auto-linked URL (<a href="URL">URL</a>) must count once.
 *
 * @see \App\Service\Cms\ArticleAdvisor
 */
class ArticleAdvisorTest extends BaseT
{
    protected function advise(string $body) : array
    {
        /** @var Factory $factory */
        $factory = static::getService(Factory::class);

        $articleEditor =
            $factory->createArticleEditor()
                ->setTitle('Article advisor probe ' . bin2hex(random_bytes(4)))
                ->setBody($body);

        /** @var ArticleAdvisor $advisor */
        $advisor = static::getService(ArticleAdvisor::class);

        return $advisor->advise($articleEditor);
    }


    public function testCleanBodyYieldsNoAdvice() : void
    {
        $arrAdvice = $this->advise('<p>Una guida a Windows con un <a href="https://example.com/pagina">link normale</a></p>');
        $this->assertSame([], $arrAdvice);
    }


    public function testGoogleDocsLinkIsReported() : void
    {
        $arrAdvice = $this->advise(
            '<p>Abbiamo preparato <a href="https://docs.google.com/document/d/1AbC/edit">la bozza condivisa</a> per il progetto</p>'
        );

        $this->assertCount(1, $arrAdvice);

        $advice = reset($arrAdvice);
        $this->assertSame(ArticleAdvisor::CHECK_GOOGLE_DOCS, $advice["id"]);
        $this->assertSame(ArticleAdvisor::LEVEL_WARNING, $advice["level"]);
        $this->assertStringContainsString("un riferimento", $advice["message"]);

        $this->assertCount(1, $advice["arrMatches"]);
        $match = reset($advice["arrMatches"]);

        // the needle lives in the href: the excerpt must still let the author find the spot
        $this->assertStringContainsString('la bozza condivisa', $match["excerpt"]);
        $this->assertStringContainsString('Abbiamo preparato', $match["excerpt"]);
        $this->assertStringContainsString(ArticleAdvisor::GOOGLE_DOCS_NEEDLE, (string)$match["url"]);
    }


    public function testGoogleDocsPlainTextIsReported() : void
    {
        $arrAdvice = $this->advise(
            '<p>Il documento è su docs.google.com/document/d/999 come promemoria</p>'
        );

        $this->assertCount(1, $arrAdvice);
        $arrMatches = reset($arrAdvice)["arrMatches"];
        $this->assertCount(1, $arrMatches);

        $match = reset($arrMatches);
        $this->assertNull($match["url"]);
        $this->assertStringContainsString('docs.google.com/document/d/999', $match["excerpt"]);
        $this->assertStringContainsString('promemoria', $match["excerpt"]);
    }


    // AutoLink stores a pasted URL as <a href="URL">URL</a>: one instance, not two
    public function testAutoLinkedUrlIsReportedOnce() : void
    {
        $url = 'https://docs.google.com/document/d/1XyZ/edit';
        $arrAdvice = $this->advise("<p>Vedi <a href=\"$url\">$url</a> per i dettagli</p>");

        $this->assertCount(1, $arrAdvice);
        $this->assertCount(1, reset($arrAdvice)["arrMatches"]);
    }


    public function testEveryInstanceIsReported() : void
    {
        $arrAdvice = $this->advise(
            '<p>Prima citazione: <a href="https://docs.google.com/document/d/1/edit">bozza uno</a></p>' .
            '<p>Un rimando testuale a docs.google.com/document/d/2 nel secondo paragrafo</p>' .
            '<h2>Un titolo pulito</h2>' .
            '<p>E un <a href="https://docs.google.com/document/d/3/edit">altro documento</a> alla fine</p>'
        );

        $this->assertCount(1, $arrAdvice);

        $advice = reset($arrAdvice);
        $this->assertCount(3, $advice["arrMatches"]);
        $this->assertStringContainsString("3 riferimenti", $advice["message"]);
    }


    public function testLongParagraphsAreExcerpted() : void
    {
        $filler = str_repeat('parole di riempimento ', 30);
        $arrAdvice = $this->advise("<p>{$filler}docs.google.com/document/d/42 {$filler}</p>");

        $arrMatches = reset($arrAdvice)["arrMatches"];
        $excerpt    = reset($arrMatches)["excerpt"];

        $this->assertStringContainsString('docs.google.com/document/d/42', $excerpt);
        $this->assertStringContainsString('…', $excerpt);
        $this->assertLessThan(220, mb_strlen($excerpt));
    }
}
