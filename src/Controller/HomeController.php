<?php
namespace App\Controller;

use App\Service\YouTubeChannelApi;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;


class HomeController extends BaseController
{
    const string SECTION_SLUG = "home";

    protected YouTubeChannelApi $YouTubeChannel;


    #[Route('/', name: 'app_home')]
    public function index(YouTubeChannelApi $YouTubeChannel) : Response
    {
        return $this->indexPaginated(1, $YouTubeChannel);
    }


    #[Route('/' . self::SECTION_SLUG . '/{page<0|1>?}', name: 'app_home_page_0-1')]
    public function appHomePage0Or1() : Response
    {
        return $this->redirectToRoute('app_home', [], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/' . self::SECTION_SLUG . '/{page<[1-9]+[0-9]*>}', name: 'app_home_paginated')]
    public function indexPaginated(?int $page, YouTubeChannelApi $YouTubeChannel) : Response
    {
        $this->YouTubeChannel = $YouTubeChannel;
        return $this->tliStandardControllerResponse(["app_home"], $page);
    }


    protected function buildHtmlNumPage(?int $page) : Response|string
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

        $arrTemplateParams = [
            'metaCanonicalUrl'  => $metaCanonicalUrl,
            'activeMenu'        => 'home',
            'FrontendHelper'    => $this->frontendHelper,
            'currentPage'       => $page,
            'Pages'             => $oPages
        ];

        //
        if( $page > 1 ) {
            return
                $this->twig->render('home/index-paginated.html.twig', array_merge($arrTemplateParams, [
                    'metaTitle' => "Archivio articoli - Pagina " . $page,
                    'Articles'  => $mainArticleCollection,
            ]));
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
        $arrVideos                  = $this->YouTubeChannel->getLatestVideos();
        $articlesMostViews          = $this->factory->createArticleCollection()->loadTopViews(1, 180);

        return
            $this->twig->render('home/index.html.twig', array_merge($arrTemplateParams, [
                'ArticlesLatestSlider'      => $arrArticlesLatestSlider,
                'ArticlesLatestMosaic1'     => $arrArticlesMosaic1,
                'ArticlesLatestMosaic2'     => $arrArticlesMosaic2,
                'TopicsLatest'              => $this->factory->createTopicCollection()->loadLatest(9),
                'ArticlesLatestSecurity'    => $this->factory->createArticleCollection()->loadLatestSecurityNews(7),
                'MiddleSlideShow'           => $arrArticlesMiddleSlideShow,
                'Videos'                    => $arrVideos,
                'ArticlesLatestMosaic3'     => $mainArticleCollection->getItems($numLatestSlider),
                'SplitArticlesMostViews'    => [
                    $articlesMostViews->getItems( $articlesMostViews->count() / 2),
                    $articlesMostViews
                ],
                'Articles'                  => $mainArticleCollection
            ]));
    }
}
