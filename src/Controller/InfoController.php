<?php
namespace App\Controller;

use App\Service\Cms\Article;
use App\Service\ServerInfo;
use DateTime;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class InfoController extends BaseController
{
    #[Route('/info', name: 'app_info')]
    public function info(ServerInfo $serverInfo): Response
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
    public function stats(): Response
    {
        return new Response("Pagina in aggiornamento", Response::HTTP_SERVICE_UNAVAILABLE);
    }


    #[Route('/calendario', name: 'app_calendar')]
    public function calendar(): Response
    {
        $now = (new DateTime());

        $articles = $this->factory->createArticleCollection()->loadFirstAndLastPublished();

        $firstArticle       = $articles->popFirst();
        $minCalendarDate    = $firstArticle?->getPublishedAt() ?? $now;

        $lastArticle        = $articles->popFirst();
        $maxCalendarDate    = $lastArticle?->getPublishedAt() ?? $now;

        return
            $this->render('info/calendar.html.twig', [
                'metaTitle'                 => 'Calendario pubblicazioni',
                'activeMenu'                => null,
                'FrontendHelper'            => $this->frontendHelper,
                'minCalendarDate'           => $minCalendarDate->modify('-1 day')->format('Y-m-d'),
                'maxCalendarDate'           => $maxCalendarDate->modify('+1 day')->format('Y-m-d'),
                'calendarEventsLoadingUrl'  => $this->generateUrl('app_calendar_events')
            ]);
    }


    #[Route('/calendario/eventi', name: 'app_calendar_events')]
    public function calendarEvents() : JsonResponse
    {
        $txtStartDate   = $this->request->get('start');
        $txtEndDate     = $this->request->get('end');

        try {
            if( empty($txtStartDate) || empty($txtEndDate) ) {
                throw new BadRequestException();
            }

            $startDate  = (new DateTime($txtStartDate))->setTime(0, 0);
            $endDate    = (new DateTime($txtEndDate))->setTime(23, 59);

            $articles   = $this->factory->createArticleCollection()->loadByPublishedDateInterval($startDate, $endDate);

        } catch (\Exception|\Error) {

            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $arrResponseData = [];

        /** @var Article $article */
        foreach($articles as $article) {

            $arrResponseData[] = [
                'title'         => html_entity_decode(
                    $article->getTitleFormatted(), ENT_QUOTES | ENT_HTML5, 'UTF-8'
                ),
                'url'           => $article->getUrl(),
                'start'         => $article->getPublishedAt()->format('Y-m-d H:i'),
                'color'         => $article->isNews() ? 'green' : 'blue'
            ];
        }

        return $this->json($arrResponseData);
    }
}
