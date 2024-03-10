<?php
namespace App\Controller;

use App\Service\Cms\Paginator;
use App\ServiceCollection\Cms\ArticleCollection;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;


class NewsController extends BaseController
{
    public function __construct(
        protected ArticleCollection $articleCollection, protected Paginator $paginator,
        RequestStack $requestStack, protected TagAwareCacheInterface $cache, protected ParameterBagInterface $parameterBag,
        protected Environment $twig
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }


    #[Route('/news', name: 'app_news')]
    public function index(): Response
    {
        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml(1);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $that = $this;
        $buildHtmlResult =
            $this->cache->get("app_news", function(CacheItem $cache) use($that) {

                $cache->tag(["app_news"]);
                $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY);
                return $that->buildHtml(1);
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    #[Route('/news/{page<0|1>}', name: 'app_news_page_0-1')]
    public function appNewsPage0Or1()
    {
        return $this->redirectToRoute('app_news', [], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/news/{page<[1-9]+[0-9]*>}', name: 'app_news_paginated')]
    public function indexPaginated(?int $page): Response
    {
        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml($page);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $that = $this;
        $cacheKey = 'app_news_page_' . $page;
        $buildHtmlResult =
            $this->cache->get($cacheKey, function(CacheItem $cache) use($that, $page) {

                $buildHtmlResult = $that->buildHtml($page);

                if( is_string($buildHtmlResult) ) {

                    $cache->tag(["app_news_" . $page, "loadLatestPublished", "loadLatestPublished_" . $page]);
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
        $this->articleCollection->loadLatestNewsPublished($page);

        $this->paginator
            ->setTotalElementsNum( $this->articleCollection->countTotalBeforePagination() )
            ->setCurrentPageNum($page)
            ->build('app_news', [], 'app_news_paginated', ['page' => $page]);

        $lastPageNum = $this->paginator->isPageOutOfRange();

        if( $lastPageNum !== false && in_array($lastPageNum, [0, 1])) {
            return $this->redirectToRoute("app_news");
        }

        if( $lastPageNum !== false ) {
            return $this->redirectToRoute("app_news_paginated", ["page" => $lastPageNum]);
        }

        $metaCanonicalUrl =
            empty($page) || $page < 2
                ? $this->generateUrl('app_news', [], UrlGeneratorInterface::ABSOLUTE_URL)
                : $this->generateUrl('app_news_paginated', ['page' => $page], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->twig->render('news/index.html.twig', [
            'metaTitle'         => 'Ultime notizie di tecnologia, sicurezza e truffe su Internet',
            'metaCanonicalUrl'  => $metaCanonicalUrl,
            'Articles'          => $this->articleCollection,
            'Paginator'         => $this->paginator
        ]);
    }
}
