<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;


class ViewsTest extends BaseT
{
    public static function viewSlugProvider() : Generator
    {
        yield from [['bozze'], ['finiti'], ['visitati'], ['commentati'], ['annuali']];
    }


    #[DataProvider('viewSlugProvider')]
    public function testViewsService($slug)
    {
        $view = static::getService('App\\Service\\Views')->get($slug);
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
        $view = static::getService('App\\Service\\Views')->get($slug);
        $this->assertEquals($url, $view['url']);
        $this->fetchHtml($url);
    }
}
