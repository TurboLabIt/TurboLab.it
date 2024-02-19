<?php
namespace App\Controller;

use App\Service\Cms\Paginator;
use App\ServiceCollection\Cms\ArticleCollection;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;


class HomeController extends BaseController
{
    public function __construct(
        protected ArticleCollection $articleCollection, protected Paginator $paginator,
        RequestStack $requestStack, protected TagAwareCacheInterface $cache, protected ParameterBagInterface $parameterBag,
        protected Environment $twig
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }



    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml(1);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $that = $this;
        $buildHtmlResult =
            $this->cache->get("app_home", function(CacheItem $cache) use($that) {

                $cache->tag(["app_home", "app_home",  "app_home_1", "loadLatestPublished", "loadLatestPublished_1"]);
                $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY);
                return $that->buildHtml(1);
        });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    #[Route('/home/{page<0|1>}', name: 'app_home_page_0-1')]
    public function appHomePage0Or1(?int $page = null)
    {
        return $this->redirectToRoute('app_home', [], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/home/{page<[1-9]+[0-9]*>}', name: 'app_home_paginated')]
    public function indexPaginated(?int $page): Response
    {
        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml($page);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $that = $this;
        $cacheKey = 'app_home_page_' . $page;
        $buildHtmlResult =
            $this->cache->get($cacheKey, function(CacheItem $cache) use($that, $page) {

                $buildHtmlResult = $that->buildHtml($page);

                if( is_string($buildHtmlResult) ) {

                    $cache->tag(["app_home_" . $page, "loadLatestPublished", "loadLatestPublished_" . $page]);
                    $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY);

                } else {

                    $cache->expiresAfter(-1);
                }

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function buildHtml(?int $page) : Response|string
    {
        $this->articleCollection->loadLatestPublished($page);

        $this->paginator
            ->setTotalElementsNum( $this->articleCollection->countTotalBeforePagination() )
            ->setCurrentPageNum($page)
            ->build('app_home', [], 'app_home_paginated', ['page' => $page]);

        $lastPageNum = $this->paginator->isPageOutOfRange();

        if( $lastPageNum !== false && in_array($lastPageNum, [0, 1])) {
            return $this->redirectToRoute("app_home");
        }

        if( $lastPageNum !== false ) {
            return $this->redirectToRoute("app_home_paginated", ["page" => $lastPageNum]);
        }

        return $this->twig->render('home/index.html.twig', [
            'metaTitle'         => "Guide PC, Windows, Android, Linux e Bitcoin",
            'metaDescription'   => "Siamo il punto di incontro italiano per apassionati di informatica. " .
                "Pubblichiamo ogni giorno tutorial per Windows, Android, Linux e Bitcoin, oltre a " .
                "guide e consigli pratici per scegliere i migliori PC, portatili e smartphone disponibili sul mercato.",
            'metaCanonicalUrl'  => strtok($this->request->getUri(), '?'),
            'metaOgType'        => 'article',
            'pageImage'         => '',
            'Articles'          => $this->articleCollection,
            'Paginator'         => $this->paginator
        ]);
    }
}
