<?php
namespace App\Controller;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;


class ViewsController extends BaseController
{
    const string SECTION_SLUG = "viste";


    #[Route('/' . self::SECTION_SLUG . '/{slug}/{page<0|1>}', name: 'app_views_multi_page_0-1')]
    public function appViewsMulti0Or1($slug) : Response
    {
        return $this->redirectToRoute('app_views_multi', ["slug" => $slug], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/' . self::SECTION_SLUG . '/{slug}/{page<[1-9]+[0-9]*>}', name: 'app_views_multi')]
    public function multi(string $slug, ?int $page = null) : Response
    {
        $arrViewRequested = $this->factory->getViews()->get($slug);

        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml($slug, $arrViewRequested, $page);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $cacheKey = "views_{$slug}_$page";
        $that = $this;

        $buildHtmlResult =
            $this->cache->get($cacheKey, function(ItemInterface $cacheItem) use($that, $slug, $arrViewRequested, $page, $cacheKey) {

                $buildHtmlResult = $that->buildHtml($slug, $arrViewRequested, $page);

                if( is_string($buildHtmlResult) ) {

                    $cacheItem->expiresAfter(static::CACHE_DEFAULT_EXPIRY);
                    $cacheItem->tag([$cacheKey, 'views', "views_$slug"]);

                } else {

                    $cacheItem->expiresAfter(-1);
                }

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function buildHtml(string $slug, array $arrViewRequested, ?int $page = null) : Response|string
    {
        $baseUrl    = $this->generateUrl('app_views_multi', ["slug" => $slug]);

        $fxLoad     = $arrViewRequested["fx"];
        $articles   = $this->factory->createArticleCollection()->$fxLoad($page);

        try {
            $oPages =
                $this->paginator
                    ->setBaseUrl($baseUrl)
                    ->buildByTotalItems($page, $articles->countTotalBeforePagination() );

        } catch(PaginatorOverflowException $ex) {

            $maxPage    = $ex->getMaxPage();
            $lastPageUrl= $baseUrl . ( empty($maxPage) || $maxPage <= 1 ? '' : "/$maxPage" );
            return $this->redirect($lastPageUrl);
        }

        $arrData = [
            'metaTitle'         => $arrViewRequested["title"] . ( $page < 2 ? '' : " - Pagina $page"),
            'breadcrumbText'    => $arrViewRequested["title"],
            'metaCanonicalUrl'  => $baseUrl . ( empty($page) || $page <= 1 ? '' : "/$page" ),
            'activeMenu'        => null,
            'FrontendHelper'    => $this->frontendHelper,
            'Articles'          => $articles,
            'Pages'             => $articles->count() > 0 ? $oPages : null,
            'currentPage'       => $page
        ];

        return $this->twig->render('views/index.html.twig', $arrData);
    }
}
