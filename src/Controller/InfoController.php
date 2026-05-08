<?php
namespace App\Controller;

use App\Exception\GoogleAnalyticsException;
use App\Service\Cms\Article;
use App\Service\GoogleAnalytics;
use App\Service\ServerInfo;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;


class InfoController extends BaseController
{
    const int CACHE_DEFAULT_EXPIRY      = 60 * 60 * 3; // 3 hours

    #[Route('/manifest.json', name: 'app_manifest')]
    public function manifest() : Response
    {
        $buildHtmlResult =
            $this->cache->get("manifest.json", function(ItemInterface $cacheItem) {

                $cacheItem->expiresAfter(3600 * 24 * 90);
                return $this->twig->render('info/manifest.json.twig');
            });

        $response = new Response($buildHtmlResult);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    #[Route('/info', name: 'app_info')]
    public function info(ServerInfo $serverInfo) : Response
    {
        return
            $this->render('info/info.html.twig', [
                'metaTitle'         => 'Informazioni tecniche',
                'activeMenu'        => null,
                'FrontendHelper'    => $this->frontendHelper,
                'ServerInfo'        => $serverInfo->getServerInfo(),
                'IssueReportGuide'  => $this->factory->createArticle()->load(Article::ID_ISSUE_REPORT),
                'SideArticles'      => $this->factory->createArticleCollection()->loadLatestPublished()
                    ->getItems(3)
            ]);
    }


    #[Route('/statistiche', name: 'app_stats')]
    public function stats(GoogleAnalytics $googleAnalytics) : Response
    {
        $isConfigured = $googleAnalytics->isConfigured();

        $arrData = [
            'metaTitle'         => 'Statistiche di traffico',
            'activeMenu'        => null,
            'FrontendHelper'    => $this->frontendHelper,
            'CurrentUser'       => $this->getCurrentUser(),
            'Stats'             => null,
            'StatsError'        => null,
            'StatsConfigured'   => $isConfigured,
            'StatsAllowedDays'  => GoogleAnalytics::ALLOWED_RANGE_DAYS,
            'StatsDefaultDays'  => GoogleAnalytics::DEFAULT_RANGE_DAYS
        ];

        if( !$isConfigured ) {
            return $this->render('info/stats.html.twig', $arrData);
        }


        if( !$this->isCachable(true) ) {

            $buildHtmlResult = $this->buildStatsHtml($googleAnalytics, $arrData);

        } else {

            $buildHtmlResult =
                $this->cache->get('app_stats', function(ItemInterface $cacheItem) use($googleAnalytics, $arrData) {

                    $buildHtmlResult = $this->buildStatsHtml($googleAnalytics, $arrData);

                    if( is_string($buildHtmlResult) ) {

                        $cacheItem->expiresAfter(static::CACHE_DEFAULT_EXPIRY);
                        $cacheItem->tag(["app_stats", "app_stats_ajax"]);

                    } else {

                        $cacheItem->expiresAfter(-1);
                    }

                    return $buildHtmlResult;
                });
        }

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function buildStatsHtml(GoogleAnalytics $googleAnalytics, array &$arrData) : Response|string
    {
        try {
            $stats = $googleAnalytics->getStatsForChart();

            // Strip staff-only sections from the payload before it reaches the template (and the embedded JSON)
            // so that non-staff users don't get the data via "view source" or via the AJAX endpoint.
            $isStaff = $this->getCurrentUser()?->isEditor() ?? false;
            if( !$isStaff ) {

                $stats['topPages']      = [];
                $stats['topTags']       = [];
                $stats['topReferrers']  = [];
            }

            $arrData["Stats"] = $stats;

        } catch(GoogleAnalyticsException $ex) {

            $arrData["StatsError"] = $ex->getMessage();
        }

        return $this->twig->render('info/stats.html.twig', $arrData);
    }


    #[Route('/ajax/statistiche', name: 'app_stats_ajax', methods: ['GET'], priority: 1)]
    public function statsAjax(GoogleAnalytics $googleAnalytics) : Response
    {
        $this->ajaxOnly();

        if( !$googleAnalytics->isConfigured() ) {
            return new JsonResponse(['error' => 'Statistiche non configurate'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $days = (int)$this->request->query->get('days', 0);


        if( !$this->isCachable(true) ) {

            $buildHtmlResult = $this->buildStatsAjax($googleAnalytics, $days);

        } else {

            $buildHtmlResult =
                $this->cache->get("app_stats_ajax_$days", function(ItemInterface $cacheItem) use($googleAnalytics, $days) {

                    $buildHtmlResult = $this->buildStatsAjax($googleAnalytics, $days);

                    if( is_string($buildHtmlResult) ) {

                        $cacheItem->expiresAfter(static::CACHE_DEFAULT_EXPIRY);
                        $cacheItem->tag(["app_stats", "app_stats_ajax"]);

                    } else {

                        $cacheItem->expiresAfter(-1);
                    }

                    return $buildHtmlResult;
                });
        }

        return is_string($buildHtmlResult)
            ? new JsonResponse($buildHtmlResult, Response::HTTP_OK, [], true)
            : $buildHtmlResult;
    }


    protected function buildStatsAjax(GoogleAnalytics $googleAnalytics, int $days) : Response|string
    {
        try {
            $stats = $googleAnalytics->getStatsForChart($days);

            // Same staff-only gating as the page render: non-staff get empty arrays for these sections.
            $isStaff = $this->getCurrentUser()?->isEditor() ?? false;
            if( !$isStaff ) {

                $stats['topPages']      = [];
                $stats['topTags']       = [];
                $stats['topReferrers']  = [];
            }

            return (string)(new JsonResponse($stats))->getContent();

        } catch(GoogleAnalyticsException $ex) {

            return new JsonResponse(['error' => $ex->getMessage()], $ex->getStatusCode());
        }
    }
}
