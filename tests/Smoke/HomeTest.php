<?php
namespace App\Tests\Smoke;

use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;


class HomeTest extends BaseT
{
    public static function homeRedirectionProvider() : Generator
    {
        yield from [
            ['/home'], ['/home/0'], ['/home/1']
        ];
    }


    #[DataProvider('homeRedirectionProvider')]
    public function testPaginationRedirectToHome(string $url)
    {
        $homeUrl = $this->generateUrl();
        $this->expectRedirect($url, $homeUrl);
    }


    public function testHomeH1()
    {
        $crawler = $this->fetchDomNode("/");
        $H1FromCrawler = $crawler->filter('body h1')->html();

        foreach(HtmlProcessor::ACCENTED_LETTERS as $accentedLetter) {

            $accentedLetterEntity = htmlentities($accentedLetter);
            $this->assertStringNotContainsString($accentedLetterEntity, $H1FromCrawler);
        }

        $this->assertEquals("Guide PC, Windows, Linux, Android e Bitcoin", $H1FromCrawler, "Homepage H1 test failed");
    }


    public function testHomeH3s()
    {
        $crawler = $this->fetchDomNode("/", 'body');
        $H3s = $crawler->filter('h3');
        $countH3 = $H3s->count();
        $this->assertGreaterThan(24, $countH3);
    }


    public function testHomeLinks()
    {
        $crawler = $this->fetchDomNode("/", 'body');
        $this->internalLinksChecker($crawler);
    }


    public function testHomeImages()
    {
        $crawler = $this->fetchDomNode("/", 'body');
        $this->internalImagesChecker($crawler);
    }


    public function testHomePaginator()
    {
        $this->internalPaginatorChecker('/', static::HOME_TOTAL_PAGES);
    }
}
