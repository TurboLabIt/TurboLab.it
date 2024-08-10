<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;


class NewsController extends BaseController
{
    #[Route('/news/{page<0|1>}', name: 'app_news_page_0-1')]
    public function appNewsPage0Or1() : Response
    {
        return $this->redirectToRoute('app_news', [], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/news/{page<[1-9]+[0-9]*>}', name: 'app_news')]
    public function index(?int $page = null): Response
    {
        return $this->tliStandardControllerResponse(["app_news"], $page);
    }


    protected function buildHtml(?int $page) : Response|string
    {
        $articleCollection = $this->factory->createArticleCollection()->loadLatestNewsPublished($page);

        try {
            $oPages =
                $this->paginator
                    ->setBaseUrl( $this->generateUrl('app_news') )
                    ->buildByTotalItems($page, $articleCollection->countTotalBeforePagination() );

        } catch(PaginatorOverflowException $ex) {
            return $this->redirectToRoute("app_news", ["page" => $ex->getMaxPage()]);
        }

        $metaTitle = 'Ultime notizie di tecnologia, sicurezza e truffe su Internet';

        if( empty($page) || $page < 2 ) {

            $arrPageParam = [];

        } else {

            $arrPageParam   = ['page' => $page];
            $metaTitle     .= " - Pagina $page";
        }

        return
            $this->twig->render('news/index.html.twig', [
                'metaTitle'         => $metaTitle,
                'metaCanonicalUrl'  => $this->generateUrl('app_news', $arrPageParam, UrlGeneratorInterface::ABSOLUTE_URL),
                'activeMenu'        => "news",
                'FrontendHelper'    => $this->frontendHelper,
                'Articles'          => $articleCollection,
                'Pages'             => $oPages,
                'currentPage'       => $page
            ]);
    }
}
