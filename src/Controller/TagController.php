<?php
namespace App\Controller;

use App\Service\Cms\Image;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;


class TagController extends BaseController
{
    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{page<0|1>}', name: 'app_tag_page_0-1')]
    public function appTagPage0Or1(string $tagSlugDashId) : Response
    {
        return $this->redirectToRoute('app_tag', ["tagSlugDashId" => $tagSlugDashId], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{page<[1-9]+[0-9]*>}', name: 'app_tag')]
    public function index(string $tagSlugDashId, ?int $page = null) : Response
    {
        $tag = $this->factory->createTag()->loadBySlugDashId($tagSlugDashId);

        $tagRealUrl = $tag->checkRealUrl($tagSlugDashId, $page);
        if( !empty($tagRealUrl) ) {
            return $this->redirect($tagRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        $taggedArticles = $tag->getArticles($page);

        try {
            $oPages =
                $this->paginator
                    ->setBaseUrl( $tag->getUrl() )
                    ->buildByTotalItems($page, $taggedArticles->countTotalBeforePagination() );

        } catch(PaginatorOverflowException $ex) {

            $lastPageUrl = $tag->getUrl( $ex->getMaxPage() );
            return $this->redirect($lastPageUrl);
        }

        $tag
            ->setClientIpAddress( $this->request->getClientIp() )
            ->countOneView();

        return $this->render('tag/index.html.twig', [
            'metaTitle'         => $tag->getTitleFormatted() . ": articoli, guide e news" . ( $page < 2 ? '' : " - Pagina $page"),
            'metaCanonicalUrl'  => $tag->getUrl($page),
            'metaPageImageUrl'  => $tag->getSpotlightOrDefaultUrlFromArticles(Image::SIZE_MAX),
            'activeMenu'        => $tag->getActiveMenu(),
            'FrontendHelper'    => $this->frontendHelper,
            'Tag'               => $tag,
            'Articles'          => $taggedArticles,
            'Pages'             => $taggedArticles->count() > 0 ? $oPages : null,
            'currentPage'       => $page
        ]);
    }


    #[Route('/tag/{tag<[^/]+>}/{page<[0-9]*>}', name: 'app_tag_legacy')]
    public function legacyUrl(string $tag, ?string $page = null) : Response
    {
        $page   = empty($page) ? null : (int)$page;
        $tag    = $this->factory->createTag()->loadByTitle($tag);
        return $this->redirect($tag->getUrl($page), Response::HTTP_MOVED_PERMANENTLY);
    }
}
