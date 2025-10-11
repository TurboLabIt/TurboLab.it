<?php
namespace App\Controller;

use App\Service\Cms\Article;
use App\Service\ServerInfo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;


class InfoController extends BaseController
{
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
    public function stats() : Response
    {
        return new Response("Pagina in aggiornamento", Response::HTTP_SERVICE_UNAVAILABLE);
    }


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
}
