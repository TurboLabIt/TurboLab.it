<?php
namespace App\Controller;

use App\Service\Cms\Article;
use App\Service\ServerInfo;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class InfoController extends BaseController
{
    #[Route('/statistiche', name: 'app_stats')]
    public function stats(): Response
    {
        return new Response("Pagina in aggiornamento", Response::HTTP_SERVICE_UNAVAILABLE);
    }


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


    #[Route('/calendario', name: 'app_calendar')]
    public function calendar(): Response
    {
        $firstLastArticle = $this->factory->createArticleCollection()->loadFirstAndLastPublished();
        $now = (new DateTime())->format('Y-m-d');

        return
            $this->render('info/calendar.html.twig', [
                'metaTitle'                 => 'Calendario pubblicazioni',
                'activeMenu'                => null,
                'FrontendHelper'            => $this->frontendHelper,
                'minCalendarDate'           => $firstLastArticle->popFirst()?->getPublishedAt()->format('Y-m-d') ?? $now,
                'maxCalendarDate'           => $firstLastArticle->popFirst()?->getPublishedAt()->format('Y-m-d') ?? $now,
                'calendarEventsLoadingUrl'  => $this->generateUrl('app_calendar_events')
            ]);
    }


    #[Route('/calendario/eventi', name: 'app_calendar_events')]
    public function calendarEvents() : JsonResponse
    {
        $txtStartDate = $this->request->get('start');
        if( !empty($txtStartDate) ) {
            $startDate = (new DateTime($txtStartDate))->setTime(0, 0);
        }

        $txtEndDate = $this->request->get('end');
        if( !empty($txtEndDate) ) {
            $endDate = (new DateTime($txtEndDate))->setTime(23, 59);
        }

        try {
            $articles = $this->factory->createArticleCollection()->loadByPublishedDateInterval($startDate, $endDate);
        } catch (\Exception) {
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
