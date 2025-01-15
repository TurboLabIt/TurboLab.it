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


    public function testCalendarAjaxPreviousMonth()
    {
        $url =
            $this->generateUrl('app_calendar_events', [
                'start' => (new \DateTime('first day of last month'))->format('Y-m-d'),
                'end'   => (new \DateTime('last day of last month'))->format('Y-m-d')
            ]);

        $this->fetchHtml($url);
    }


    public function testCalendarAjaxNoStartDate()
    {
        $url =
            $this->generateUrl('app_calendar_events', [
                'start' => null,
                'end'   => (new \DateTime('last day of last month'))->format('Y-m-d')
            ]);

        $this->browse($url);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }


    public function testCalendarAjaxNoEndDate()
    {
        $url =
            $this->generateUrl('app_calendar_events', [
                'start' => (new \DateTime('first day of last month'))->format('Y-m-d'),
                'end'   => null
            ]);

        $this->browse($url);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }


    public function testCalendarAjaxMalformedStartDate()
    {
        $url =
            $this->generateUrl('app_calendar_events', [
                'start' => 'bad-start-date',
                'end'   => (new \DateTime('last day of last month'))->format('Y-m-d')
            ]);

        $this->browse($url);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }



    public function testCalendarAjaxMalformedEndDate()
    {
        $url =
            $this->generateUrl('app_calendar_events', [
                'start' => (new \DateTime('first day of last month'))->format('Y-m-d'),
                'end'   => 'bad-end-date',
            ]);

        $this->browse($url);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }


    public function testCalendarAjaxTooWideDateRange()
    {
        $url =
            $this->generateUrl('app_calendar_events', [
                'start' => (new \DateTime('first day of last month'))->modify('-2 months')->format('Y-m-d'),
                'end'   => (new \DateTime('last day of last month'))->format('Y-m-d')
            ]);

        $this->browse($url);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }


    public function testCalendarAjaxSwappedDates()
    {
        $url =
            $this->generateUrl('app_calendar_events', [
                'start' => (new \DateTime('last day of last month'))->format('Y-m-d'),
                'end'   => (new \DateTime('first day of last month'))->format('Y-m-d')
            ]);

        $this->browse($url);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
