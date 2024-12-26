<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;


class InfoTest extends BaseT
{
    public static function infoRouteProvider() : Generator
    {
        yield from [
            ['app_info'], ['app_calendar']/*, ['app_stats']*/
        ];
    }


    public function testStats503()
    {
        $url = $this->generateUrl('app_stats');
        $this->browse($url);
        $this->assertResponseStatusCodeSame( Response::HTTP_SERVICE_UNAVAILABLE, "Failing URL: $url");
    }


    #[DataProvider('infoRouteProvider')]
    public function testInfoPageOK(string $route)
    {
        $url = $this->generateUrl($route);
        $this->fetchHtml($url);
    }


    public function testCalendarPreviousMonth()
    {
        $url =
            $this->generateUrl('app_calendar_events', [
                'start' => (new \DateTime('first day of last month'))->format('Y-m-d'),
                'end'   => (new \DateTime('last day of last month'))->format('Y-m-d')
            ]);

        $this->fetchHtml($url);
    }
}
