<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;


/**
 * The 300x600 sidebar sticky (templates/ads/clickio/sticky-sidebar.html.twig) must be the last item of the side column
 * (parts/column-articles.html.twig): it stays pinned while the article scrolls by, so anything after it in the column
 * would slide under it. The column is shared by the article page and /info.
 *
 * Readers only: whoever can edit the article gets the images gallery instead of this column, and no ads (hasAds).
 * That branch is the article template's, untouched by the ad, and a KernelBrowser login can't reach it (stateless
 * firewall), so it isn't exercised here.
 */
class ArticleSidebarAdTest extends BaseT
{
    public function testArticleSideColumnEndsWithTheStickyAd() : void
    {
        $this->assertSideColumnEndsWithTheStickyAd( static::getArticle()->getUrl() );
    }


    public function testInfoSideColumnEndsWithTheStickyAd() : void
    {
        $this->assertSideColumnEndsWithTheStickyAd( $this->generateUrl('app_info') );
    }


    private function assertSideColumnEndsWithTheStickyAd(string $url) : void
    {
        $articleList = $this->fetchDomNode($url, '.about-tab-btn');
        $this->assertCount(1, $articleList, "The side column's article list is missing on $url");

        $column = $articleList->ancestors()->first();
        $this->assertStringContainsString('col-lg-4', (string)$column->attr('class'), "Unexpected side column on $url");

        $clickioBlocks = $column->filter('script.__lxGc__');
        $this->assertCount(1, $clickioBlocks, "The side column of $url must carry exactly one Clickio ad block");

        $lastItem = $column->children()->last();
        $this->assertSame('script', $lastItem->nodeName(), "The sticky ad must be the last item of the side column on $url");
        $this->assertStringContainsString('__lxGc__', (string)$lastItem->attr('class'), "The last item of the side column on $url must be the Clickio ad block");
    }
}
