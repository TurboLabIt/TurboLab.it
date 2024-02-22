<?php
namespace App\Tests\Smoke;

use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use Symfony\Component\DomCrawler\Crawler;


class HomeTest extends BaseT
{
    const HOME_TOTAL_PAGES = 160;


    public static function homeRedirectionProvider()
    {
        yield ['/home', '/home/0', '/home/1'];
    }


    /**
     * @dataProvider homeRedirectionProvider
     */
    public function testPaginationRedirectToHome(string $url)
    {
        $this->expectRedirect($url, $_ENV["APP_SITE_URL"]);
    }


    public function testHomeFirstPage()
    {
        $url = "/";

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker($crawler, "Guide PC, Windows, Android, Linux e Bitcoin");

        // H2
        $crawler = $this->fetchDomNode($url, 'article');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(24, $countH2);

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker($_ENV["APP_SITE_URL"], static::HOME_TOTAL_PAGES);
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
