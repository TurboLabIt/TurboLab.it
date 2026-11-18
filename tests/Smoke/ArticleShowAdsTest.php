<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;


/**
 * article.show_ads is the article's own ad switch, kept from TLI1, where it was set by hand: there is no UI for it. It is
 * off on the few articles no ad may run on (adult news, a YouTube downloader, the Windows ISO guides, the sponsors'
 * giveaways, the donations page) and on ID_QUALITY_TEST. Such an article gets no ad at all, as a viewer who can edit it
 * does: no Clickio header code or consent tool, no ad unit, no ad-blocker warning. The newest article with ads is the
 * positive control, so the "none" below isn't vacuous.
 */
class ArticleShowAdsTest extends BaseT
{
    public function testArticleWithAdsOffServesNone() : void
    {
        $article = static::getArticle();
        $this->assertFalse($article->showAds(), "This test relies on the quality-test article having its ads off (show_ads = 0)");

        $url = $article->getUrl();
        foreach($this->countAdParts( $this->fetchDomNode($url) ) as $part => $count) {
            $this->assertSame(0, $count, "$url has its ads off (show_ads = 0), yet it carries $part");
        }

        $urlWithAds = static::getArticleWithAds()->getUrl();
        foreach($this->countAdParts( $this->fetchDomNode($urlWithAds) ) as $part => $count) {
            $this->assertGreaterThan(0, $count, "Positive control: $urlWithAds has its ads on, so it must carry $part");
        }
    }
}
