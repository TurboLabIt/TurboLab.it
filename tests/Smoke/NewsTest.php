<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;


class NewsTest extends BaseT
{
    public static function newsRedirectionProvider() : Generator
    {
        yield from [
            ['/news/0'], ['/news/1']
        ];
    }


    #[DataProvider('newsRedirectionProvider')]
    public function testPaginationRedirectToNews(string $url)
    {
        $homeUrl = $this->generateUrl('app_news');
        $this->expectRedirect($url, $homeUrl);
    }


    public function testNewsFirstPage()
    {
        $url = "/news";

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker($crawler, "Ultime notizie di tecnologia, programmi e sicurezza su Internet");

        // H2
        $crawler = $this->fetchDomNode($url, 'body');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(24, $countH2);

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker("/news", static::NEWS_TOTAL_PAGES);
    }


    protected function tagTitleAsH1Checker(Crawler $crawler, ?string $expectedH1) : void
    {
        $H1FromCrawler = $crawler->filter('body h1')->html();
        $this->assertNoLegacyEntities($H1FromCrawler);
        $this->assertEquals($expectedH1, $H1FromCrawler, "Explicit H1 check failure!");
    }
}
