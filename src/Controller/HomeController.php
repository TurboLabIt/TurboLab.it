<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;


class HomeController extends BaseController
{
    #[Route('/', name: 'app_home')]
    public function index() : Response { return $this->indexPaginated(1); }


    #[Route('/home/{page<0|1>?}', name: 'app_home_page_0-1')]
    public function appHomePage0Or1() : Response
    {
        return $this->redirectToRoute('app_home', [], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/home/{page<[1-9]+[0-9]*>}', name: 'app_home_paginated')]
    public function indexPaginated(?int $page): Response
    {
        return $this->tliStandardControllerResponse(["app_home"], $page);
    }


    protected function buildHtml(?int $page) : Response|string
    {
        $mainArticleCollection = $this->factory->createArticleCollection()->loadLatestPublished($page);

        try {
            $oPages =
                $this->paginator
                    ->setBaseUrl( $this->generateUrl('app_home') )
                    ->setBaseUrlForPages( $this->generateUrl('app_home_page_0-1') )
                    ->buildByTotalItems($page, $mainArticleCollection->countTotalBeforePagination() );

        } catch(PaginatorOverflowException $ex) {
            return $this->redirectToRoute("app_home_paginated", ["page" => $ex->getMaxPage()]);
        }

        $metaCanonicalUrl =
            empty($page) || $page < 2
                ? $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL)
                : $this->generateUrl('app_home_paginated', ['page' => $page], UrlGeneratorInterface::ABSOLUTE_URL);

        /**
         * no trailing slash for the canonical homepage URL, even if this is not really needed:
         * "Rest assured that for your root URL specifically, https://example.com is equivalent to https://example.com/
         *   and can't be redirected even if you're Chuck Norris."
         * 📚 https://developers.google.com/search/blog/2010/04/to-slash-or-not-to-slash
         */
        $metaCanonicalUrl = trim($metaCanonicalUrl, '/');

        //
        if( $page > 1 ) {

            return $this->twig->render('home/index-paginated.html.twig', [
                'metaTitle'                 => "Archivio articoli - Pagina " . $page,
                'metaCanonicalUrl'          => $metaCanonicalUrl,
                'activeMenu'                => 'home',
                'Articles'                  => $mainArticleCollection,
                'Pages'                     => $oPages,
                'currentPage'               => $page,
                'GuidesForAuthors'          => $this->factory->createArticleCollection()->loadGuidesForAuthors()
            ]);
        }

        $numLatestSlider = 4;
        $arrArticlesLatestSlider = $mainArticleCollection->getItems($numLatestSlider);

        //
        $arrArticlesMosaic1 = $arrArticlesMosaic2 = [];
        $numMosaic = $numLatestSlider + 4;
        for($i=0; $i<$numMosaic; $i++) {

            $article =  $mainArticleCollection->popFirst();
            if( empty($article) ) {
                break;
            }

            if( $i == 0 || $i % 2 == 0 ) {
                $arrArticlesMosaic1[] = $article;
            } else {
                $arrArticlesMosaic2[] = $article;
            }
        }

        //
        $arrArticlesMiddleSlideShow = $mainArticleCollection->getItems($numLatestSlider);
        $arrVideos                  = $this->YouTubeChannel->getLatestVideos(8);
        $articlesMostViews          = $this->factory->createArticleCollection()->loadTopViewsRecent();

        return $this->twig->render('home/index.html.twig', [
            'metaCanonicalUrl'          => $metaCanonicalUrl,
            'activeMenu'                => 'home',
            'ArticlesLatestSlider'      => $arrArticlesLatestSlider,
            'ArticlesLatestMosaic1'     => $arrArticlesMosaic1,
            'ArticlesLatestMosaic2'     => $arrArticlesMosaic2,
            'TopicsLatest'              => $this->factory->createTopicCollection()->loadLatest(),
            'ArticlesLatestSecurity'    => $this->factory->createArticleCollection()->loadLatestSecurityNews(),
            'MiddleSlideShow'           => $arrArticlesMiddleSlideShow,
            'Videos'                    => $arrVideos,
            'ArticlesLatestMosaic3'     => $mainArticleCollection->getItems($numLatestSlider),
            'SplitArticlesMostViews'    => [
                $articlesMostViews->getItems( $articlesMostViews->count() / 2),
                $articlesMostViews
            ],
            'Articles'                  => $mainArticleCollection,
            'Categories'                => $this->factory->createTagCollection()->loadCategories(),
            'GuidesForAuthors'          => $this->factory->createArticleCollection()->loadGuidesForAuthors(),
            'Pages'                     => $oPages
        ]);
    }
}
