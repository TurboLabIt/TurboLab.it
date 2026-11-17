<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;


/**
 * The 300x600 sidebar sticky (templates/ads/clickio/sticky-sidebar.html.twig) must be the last item of the side column
 * (parts/column-articles.html.twig): it stays pinned while the article scrolls by, so anything after it in the column
 * would slide under it. The column is shared by the article page and /info.
 *
 * Clickio's own config for the unit names its borders by CSS selector: it pins below #tli-side-articles (s_s_top) and
 * stops above "#tli-comments-box, footer" (s_s_bot), and renders nothing when either matches nothing on the page.
 * TLI1's selector, left in that config, matched nothing here and kept the unit blank.
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
        $articleList = $this->fetchDomNode($url, '#tli-side-articles');
        $this->assertCount(1, $articleList,
            "#tli-side-articles is missing on $url: Clickio's sidebar sticky pins below it, and renders nothing without it");

        $column = $articleList->ancestors()->first();
        $this->assertStringContainsString('col-lg-4', (string)$column->attr('class'), "Unexpected side column on $url");
        $this->assertSame('tli-side-articles', $column->children()->first()->attr('id'),
            "The article list must open the side column on $url");

        $clickioBlocks = $column->filter('script.__lxGc__');
        $this->assertCount(1, $clickioBlocks, "The side column of $url must carry exactly one Clickio ad block");

        $lastItem = $column->children()->last();
        $this->assertSame('script', $lastItem->nodeName(), "The sticky ad must be the last item of the side column on $url");
        $this->assertStringContainsString('__lxGc__', (string)$lastItem->attr('class'), "The last item of the side column on $url must be the Clickio ad block");
    }
}
