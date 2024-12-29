<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;


class ViewsTest extends BaseT
{
    public static function viewSlugProvider() : Generator
    {
        yield from [['bozze'], ['finiti']];
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
        $this->fetchHtml($url);
    }


    public static function legacyViewSlugProvider() : Generator
    {
        yield from [['tutti']];
    }


    #[DataProvider('legacyViewSlugProvider')]
    public function testLegacyViewRedirect(string $slug)
    {
        $url = $this->generateUrl('app_views_legacy', ['slug' => $slug]);
        $this->expectRedirect($url, '/');

        foreach([null, 0, 1, 2, 3, 10, 11, 99, 247] as $pageNum) {

            $url = $this->generateUrl('app_views_legacy', ['slug' => $slug]) . "/$pageNum";
            $this->expectRedirect($url, '/');
        }
    }
}
