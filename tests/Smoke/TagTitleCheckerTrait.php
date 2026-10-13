<?php
namespace App\Tests\Smoke;

use App\Service\Cms\Tag;
use Symfony\Component\DomCrawler\Crawler;


trait TagTitleCheckerTrait
{
    protected function tagTitleAsH1Checker(Tag $tag, Crawler $crawler, ?string $expectedH1 = null) : void
    {
        $assertFailureMessage = "Failing URL: " . $tag->getUrl();

        $tagTitle = $tag->getNavTitle();
        $this->assertNotEmpty($tagTitle, $assertFailureMessage);
        $this->assertNoLegacyEntities($tagTitle);

        // getNavTitle() now returns the RAW tag title; the <h1> is auto-escaped on render. The DomCrawler
        // serializes & < > as entities (quotes stay raw), matching htmlspecialchars(ENT_NOQUOTES).
        $tagTitleAsRendered = htmlspecialchars($tagTitle, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
        $H1FromCrawler = $crawler->filter('body h1')->html();
        $this->assertEquals("$tagTitleAsRendered: articoli, guide e news", $H1FromCrawler, $assertFailureMessage);

        if( $expectedH1 !== null ) {
            $this->assertEquals($expectedH1, $H1FromCrawler, "Explict H1 check failure! " . $assertFailureMessage);
        }
    }
}
