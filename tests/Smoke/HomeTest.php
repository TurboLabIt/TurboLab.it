<?php
namespace App\Tests\Smoke;

use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;


class HomeTest extends BaseT
{
    const HOME_TOTAL_PAGES = 161;


    public static function homeRedirectionProvider()
    {
        yield ['/home', '/home/0', '/home/1'];
    }


    #[DataProvider('homeRedirectionProvider')]
    public function testPaginationRedirectToHome(string $url)
    {
        $this->expectRedirect($url, $_ENV["APP_SITE_URL"]);
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
        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker($_ENV["APP_SITE_URL"], static::HOME_TOTAL_PAGES);
    }


    public function testHomeImages()
    {
        $crawler = $this->fetchDomNode("/", 'body');
        $this->internalImagesChecker($crawler);
    }


    public function testHomePaginator()
    {
        $this->internalPaginatorChecker($_ENV["APP_SITE_URL"], static::HOME_TOTAL_PAGES);
    }

}
