<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;


class ViewsTest extends BaseT
{
    public static function legacyViewsUrlProvider() : Generator
    {
        yield from [['tutti']];
    }


    #[DataProvider('legacyViewsUrlProvider')]
    public function testLegacyViewRedirect(string $slug)
    {
        $url = $this->generateUrl('app_views_legacy', ['slug' => $slug]);
        $this->expectRedirect($url, '/');

        foreach([null, 0, 1, 2, 3, 10, 11, 99, 247] as $pageNum) {

            $url = $this->generateUrl('app_views_legacy', ['slug' => $slug, 'page' => $pageNum]);
            $this->expectRedirect($url, '/');
        }
    }
}
