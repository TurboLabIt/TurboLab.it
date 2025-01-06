<?php
namespace App\Tests\Smoke;

use App\Controller\SearchController;
use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;


class SearchTest extends BaseT
{
    public static function legacySearchProvider() : Generator
    {
        yield from [
            ['/cerca/', '/'], ['/cerca', '/'],
            ['/cerca/?query=', '/'], ['/cerca?query=', '/'],
            ['/cerca/?query=windows', '/cerca/windows'], ['/cerca?query=windows', '/cerca/windows'],
        ];
    }


    #[DataProvider('legacySearchProvider')]
    public function testLegacyRedirect(string $origin, $expected)
    {
        $this->expectRedirect($origin, $expected);
    }


    public static function searchProvider() : Generator
    {
        yield from [
            ['/cerca/windows 11 iso', 'Scaricare Windows 11 DVD/ISO'],
            ['/cerca/windows su usb', 'installare Windows 11 o Windows 10 su chiavetta USB'],
            ['/cerca/siti torrent', 'Siti BitTorrent italiani']
        ];
    }


    #[DataProvider('searchProvider')]
    public function testSearch(string $urlToFetch, string $mustContain)
    {
        $crawler = $this->fetchDomNode($urlToFetch);

        foreach(['.google-result' => 5, '.local-result' => 0] as $container => $minResults) {

            $results = $crawler->filter($container);
            $count   = $results->count();
            $this->assertGreaterThan($minResults, $count);

            $html =
                implode('', $results->each(function ($nodeCrawler) {
                    return $nodeCrawler->html();
                }));

            $text = strip_tags($html);

            $this->assertStringContainsString($mustContain, $text, "Failing URL: $urlToFetch");
        }

        $html = $crawler->html();
        $this->assertStringNotContainsString(SearchController::NO_RESULTS_MESSAGE, $html, "Failing URL: $urlToFetch");
    }
}
