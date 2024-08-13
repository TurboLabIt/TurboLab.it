<?php
namespace App\Tests\Smoke;

use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;


class NewsTest extends BaseT
{
    const NEWS_TOTAL_PAGES = 41;


    public static function newsRedirectionProvider()
    {
        yield ['/news/0', '/news/1'];
    }


    #[DataProvider('newsRedirectionProvider')]
    public function testPaginationRedirectToNews(string $url)
    {
        $this->expectRedirect($url, $_ENV["APP_SITE_URL"] . 'news');
    }


    public function testNewsFirstPage()
    {
        $url = "/news";

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker($crawler, "Ultime notizie di tecnologia, sicurezza e truffe su Internet");

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

        foreach(HtmlProcessor::ACCENTED_LETTERS as $accentedLetter) {

            $accentedLetterEntity = htmlentities($accentedLetter);
            $this->assertStringNotContainsString($accentedLetterEntity, $H1FromCrawler);
        }

        $this->assertEquals($expectedH1, $H1FromCrawler, "Explicit H1 check failure!");
    }
}
