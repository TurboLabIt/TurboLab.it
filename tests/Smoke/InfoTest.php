<?php
namespace App\Tests\Smoke;

use App\Service\GoogleAnalytics;
use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class InfoTest extends BaseT
{
    public static function infoRouteProvider() : Generator
    {
        yield from [
            ['app_info'], ['app_calendar']
        ];
    }


    #[DataProvider('infoRouteProvider')]
    public function testInfoPageOK(string $route)
    {
        $url = $this->generateUrl($route);
        $this->fetchHtml($url);
    }


    public function testStatsPageRendersChartsAndHeadlines() : void
    {
        $url     = $this->generateUrl('app_stats');
        $crawler = $this->browse($url);
        $this->assertResponseIsSuccessful("Failing URL: $url");

        // If GA isn't configured (e.g. CI without the secret), the page returns 200 but
        // only shows the "non configurate" fallback — bail out without further checks.
        if( $crawler->filter('#tli-stats-range-selector')->count() === 0 ) {
            $this->markTestSkipped('Stats page rendered the "non configurate" fallback (no GA property/credentials available).');
        }

        // 6 chart canvases must be present
        $canvasIds = [
            'tli-chart-pageviews', 'tli-chart-activeusers', 'tli-chart-registeredusers',
            'tli-chart-newsletter', 'tli-chart-forumposts', 'tli-chart-articlespublished'
        ];

        foreach( $canvasIds as $id ) {
            $this->assertCount(1, $crawler->filter("#$id"), "Missing chart canvas #$id");
        }

        // 6 headline placeholder slots must exist (their textual values are filled in by JS at runtime)
        foreach( ['pageViews', 'activeUsers', 'pageViewsAvg', 'activeUsersAvg', 'registeredUsers', 'articlesPublished'] as $key ) {
            $this->assertCount(1, $crawler->filter('[data-tli-headline="' . $key . '"]'),
                "Missing headline slot data-tli-headline=\"$key\"");
        }

        // 4 Periodo buttons (one per allowed range) — these drive the AJAX refresh
        foreach( GoogleAnalytics::ALLOWED_RANGE_DAYS as $days ) {
            $this->assertCount(1, $crawler->filter('.tli-stats-range-pill[data-days="' . $days . '"]'),
                "Missing Periodo button for $days days");
        }

        // The selector exposes the AJAX URL the JS must call
        $ajaxUrl = $this->generateUrl('app_stats_ajax', [], UrlGeneratorInterface::ABSOLUTE_PATH);
        $this->assertSame($ajaxUrl, $crawler->filter('#tli-stats-range-selector')->attr('data-ajax-url'),
            'Range selector data-ajax-url does not match the app_stats_ajax route');

        // The embedded JSON block must carry initial totals so the headline fills in immediately on load
        $jsonText = $crawler->filter('#tli-stats-initial-data')->text();
        $stats    = json_decode($jsonText, true);
        $this->assertIsArray($stats, 'tli-stats-initial-data did not contain valid JSON');
        $this->assertArrayHasKey('totals', $stats);
        $this->assertArrayHasKey('range',  $stats);

        foreach( ['pageViews', 'activeUsers', 'registeredUsers', 'newsletterSubscribers', 'forumPosts', 'articlesPublished'] as $key ) {
            $this->assertArrayHasKey($key, $stats);
            $this->assertArrayHasKey($key, $stats['totals'], "totals.$key missing");
            $this->assertArrayHasKey('current', $stats['totals'][$key], "totals.$key.current missing");
        }
    }


    /**
     * The Periodo buttons fire AJAX requests to /ajax/statistiche?days=N.
     * Verify the endpoint accepts every allowed range and returns the expected JSON shape.
     */
    public function testStatsAjaxAcceptsAllAllowedRanges() : void
    {
        if( !static::getService(GoogleAnalytics::class)->isConfigured() ) {
            $this->markTestSkipped('GoogleAnalytics not configured for the test environment.');
        }

        $baseUrl = $this->generateUrl('app_stats_ajax', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        foreach( GoogleAnalytics::ALLOWED_RANGE_DAYS as $days ) {

            $url = $baseUrl . '?days=' . $days;
            $this->browse($url);
            $this->assertResponseIsSuccessful("AJAX failed for days=$days at $url");
            $this->assertStringStartsWith('application/json', (string)static::$client->getResponse()->headers->get('content-type'),
                "Wrong content-type for days=$days");

            $payload = json_decode((string)static::$client->getResponse()->getContent(), true);
            $this->assertIsArray($payload, "Invalid JSON for days=$days");

            foreach( ['range', 'totals', 'pageViews', 'activeUsers', 'registeredUsers', 'newsletterSubscribers', 'forumPosts', 'articlesPublished'] as $key ) {
                $this->assertArrayHasKey($key, $payload, "AJAX missing key '$key' for days=$days");
            }

            $this->assertSame($days, $payload['range']['days'] ?? null,
                "AJAX range.days mismatch for days=$days");
        }
    }


    /**
     * Regression test for the staff-only gating: the default test client is anonymous and must
     * NOT see some metrics, neither in the rendered HTML nor in the
     * AJAX response. This guards against a future change accidentally dropping the gate.
     */
    public function testStatsHidesGatedDataFromAnonymousVisitor() : void
    {
        if( !static::getService(GoogleAnalytics::class)->isConfigured() ) {
            $this->markTestSkipped('GoogleAnalytics not configured for the test environment.');
        }

        $url     = $this->generateUrl('app_stats');
        $crawler = $this->browse($url);
        $this->assertResponseIsSuccessful();

        // The three gated cards must not be rendered for non-staff
        foreach( ['tli-stats-card-toppages', 'tli-stats-card-toptags', 'tli-stats-card-topreferrers'] as $cardId ) {
            $this->assertCount(0, $crawler->filter("#$cardId"),
                "Gated card '#$cardId' was rendered to a non-staff visitor");
        }

        // Embedded Stats JSON must also have empty arrays for the gated keys
        $stats = json_decode($crawler->filter('#tli-stats-initial-data')->text(), true);
        $this->assertIsArray($stats);

        foreach( ['topPages', 'topTags', 'topReferrers' ] as $key ) {
            $this->assertSame([], $stats[$key] ?? null,
                "Embedded Stats JSON leaked '$key' to a non-staff visitor");
        }

        // The AJAX endpoint must apply the same gating
        $ajaxUrl = $this->generateUrl('app_stats_ajax', [], UrlGeneratorInterface::ABSOLUTE_PATH) . '?days=7';
        $this->browse($ajaxUrl);
        $this->assertResponseIsSuccessful();

        $payload = json_decode((string)static::$client->getResponse()->getContent(), true);
        $this->assertIsArray($payload);

        foreach( ['topPages', 'topTags', 'topReferrers'] as $key ) {
            $this->assertSame([], $payload[$key] ?? null,
                "AJAX response leaked '$key' to a non-staff visitor");
        }
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
