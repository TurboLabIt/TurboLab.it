<?php
namespace App\Tests\Smoke;

use App\Service\Cms\Article;
use App\Service\Cms\Paginator;
use App\Service\Views;
use App\ServiceCollection\Cms\ArticleCollection;
use App\Tests\BaseT;
use DateTime;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;


class ViewsTest extends BaseT
{
    public static function viewSlugProvider() : Generator
    {
        yield from [['bozze'], ['finiti'], ['tendenza'], ['visitati'], ['commentati'], ['refresh/annuali'], ['refresh/ubuntu']];
    }


    public static function trendingWindowProvider() : Generator
    {
        // the wide window surely holds re-published old articles: they must not top the list with their lifetime views
        yield from [[ArticleCollection::TRENDING_DAYS], [365]];
    }


    #[DataProvider('trendingWindowProvider')]
    public function testTrendingRanksByViewsSincePublished(int $maxDaysAgo)
    {
        $arrExpectedIds =
            static::getEntityManager()->getConnection()->fetchFirstColumn("
                SELECT id FROM article
                WHERE publishing_status = :published AND published_at <= NOW() AND published_at >= :daysAgo
                ORDER BY (
                    SELECT COUNT(*) FROM visit WHERE visit.article_id = article.id AND visit.updated_at >= article.published_at
                ) DESC, published_at DESC, id DESC
            ", [
                'published' => Article::PUBLISHING_STATUS_PUBLISHED,
                'daysAgo'   => (new DateTime("-$maxDaysAgo days"))->format('Y-m-d H:i:s')
            ]);

        $arrExpectedIds     = array_map('intval', $arrExpectedIds);
        $articlesPerPage    = static::getService(Paginator::class)->getItemsPerPageNum();

        foreach([1, 2] as $page) {

            $articles = static::getArticleCollection()->loadTrending($page, $maxDaysAgo);

            $this->assertEquals(count($arrExpectedIds), $articles->countTotalBeforePagination());
            $this->assertSame(
                array_slice($arrExpectedIds, $articlesPerPage * ($page - 1), $articlesPerPage),
                array_keys( $articles->getAll() )
            );
        }
    }


    #[DataProvider('viewSlugProvider')]
    public function testViewsService($slug)
    {
        $view = static::getService(Views::class)->get($slug);
        $this->assertIsArray($view);
        $this->assertArrayHasKey('title', $view);
        $this->assertArrayHasKey('url', $view);
    }


    #[DataProvider('viewSlugProvider')]
    public function testPagination0or1RedirectsToView(string $slug)
    {
        foreach([0, 1] as $pageNum) {

            $url = $this->generateUrl('app_views_multi', ['slug' => $slug]) . "/$pageNum";
            $expectedUrl = $this->generateUrl('app_views_multi', ['slug' => $slug]);
            $this->expectRedirect($url, $expectedUrl);
        }
    }


    #[DataProvider('viewSlugProvider')]
    public function testViewOK(string $slug)
    {
        $url = $this->generateUrl('app_views_multi', ['slug' => $slug]);
        $view = static::getService(Views::class)->get($slug);
        $this->assertEquals($url, $view['url']);
        $this->fetchHtml($url);
    }
}
