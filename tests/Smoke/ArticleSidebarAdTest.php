<?php
namespace App\Tests\Smoke;

use App\ServiceCollection\Cms\ArticleCollection;
use App\Tests\BaseT;
use Symfony\Component\DomCrawler\Crawler;


/**
 * The 300x600 sidebar sticky (templates/ads/clickio/sticky-sidebar.html.twig) must be the last item of the side column
 * (parts/column-articles.html.twig): it stays pinned while the article scrolls by, so anything after it in the column
 * would slide under it. /info shares the column, without the ad: the page is too short for it to pin.
 *
 * Clickio's own config for the unit, unchanged since TLI1, names its borders by CSS selector: it pins below
 * STICKY_TOP_BORDER and stops above the first footer on the page, and renders nothing when either matches nothing
 * (which kept it blank at first). So the column is a list of sidebar widgets, as on TLI1, and the article has its own
 * footer, ahead of the full-width comments: with only the site's footer, the pinned ad would ride over them.
 *
 * Readers only: whoever can edit the article gets the images gallery instead of this column, and no ads (hasAds).
 * That branch is the article template's, untouched by the ad, and a KernelBrowser login can't reach it (stateless
 * firewall), so it isn't exercised here.
 */
class ArticleSidebarAdTest extends BaseT
{
    // verbatim from Clickio's config for the unit (s_s_top)
    private const string STICKY_TOP_BORDER = 'ul.sidebar_list>li.widget.widget-sidebar:nth-last-of-type(1)';


    public function testArticleSideColumnEndsWithTheStickyAd() : void
    {
        $page = $this->assertSideColumnEndsWithTheStickyAd( static::getArticle()->getUrl() );

        $this->assertNotNull($page->filter('footer')->first()->closest('article'),
            "The first footer on the article page must be the article's own: the pinned sidebar ad stops above it");
    }


    public function testInfoSideColumnHasNoAd() : void
    {
        $url    = $this->generateUrl('app_info');
        $column = $this->fetchDomNode($url, '.col-lg-4:has(> ul.sidebar_list)');
        $this->assertCount(1, $column, "The side column is missing on $url");
        $this->assertCount(0, $column->filter('script.__lxGc__'), "$url is too short for the sidebar sticky ad");

        $this->assertStringContainsString('clickio', $this->fetchHtml($url), "$url keeps the other Clickio ads");
    }


    private function assertSideColumnEndsWithTheStickyAd(string $url) : Crawler
    {
        $page = $this->fetchDomNode($url);

        $topBorder = $page->filter(self::STICKY_TOP_BORDER);
        $this->assertCount(1, $topBorder,
            "Clickio's sidebar sticky pins below " . self::STICKY_TOP_BORDER . ", which must match once on $url");

        $column = $topBorder->closest('.col-lg-4') ?? $this->fail("The sidebar widgets must sit in the side column on $url");

        $this->assertLessThanOrEqual(ArticleCollection::SIDEBAR_MAX_ARTICLES, $column->filter('.trending-news-item')->count(),
            "Too many side articles on $url: the sticky ad below them would show up later");

        $this->assertCount(1, $column->filter('script.__lxGc__'), "The side column of $url must carry exactly one Clickio ad block");

        $lastItem = $column->children()->last();
        $this->assertSame('script', $lastItem->nodeName(), "The sticky ad must be the last item of the side column on $url");
        $this->assertStringContainsString('__lxGc__', (string)$lastItem->attr('class'),
            "The last item of the side column on $url must be the Clickio ad block");

        return $page;
    }
}
