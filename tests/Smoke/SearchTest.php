<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;


class SearchTest extends BaseT
{
    public static function legacySearchProvider() : Generator
    {
        yield from [
            ['/cerca/', '/cerca'], ['/cerca', '/'],
            ['/cerca/?query=', '/cerca?query='], ['/cerca?query=', '/'],
            ['/cerca/?query=windows', '/cerca?query=windows'], ['/cerca?query=windows', '/cerca/windows'],
        ];
    }


    #[DataProvider('legacySearchProvider')]
    public function testLegacyRedirect(string $origin, $expected)
    {
        $this->expectRedirect($origin, $expected);
    }


    public static function searchProvider() : Generator
    {
        yield from [['/cerca/windows']];
    }


    #[DataProvider('searchProvider')]
    public function testSearch(string $url)
    {
        $this->fetchHtml($url);
    }
}
