<?php
namespace App\Controller;

use App\Service\Cms\Image;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;


class TagController extends BaseController
{
    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{page<0|1>}', name: 'app_tag_page_0-1')]
    public function appTagPage0Or1(string $tagSlugDashId)
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
            'metaTitle'         => "#" . $tag->getTitle() . ": articoli, guide e news",
            'metaDescription'   => "Articoli, guide, notizie che riguardano: " . $tag->getTitle(),
            'metaCanonicalUrl'  => $tag->getUrl($page),
            'metaOgType'        => 'article',
            'metaPageImageUrl'  => $tag->getSpotlightOrDefaultUrlFromArticles(Image::SIZE_MAX),
            'Tag'               => $tag,
            'Articles'          => $taggedArticles,
            'Pages'             => $oPages,
            'currentPage'       => $page,
            'GuidesForAuthors'  => $this->factory->createArticleCollection()->loadGuidesForAuthors(),
        ]);
    }


    #[Route('/tag/{tag<[^/]+>}/{page<[0-9]*>}', name: 'app_tag_legacy')]
    public function legacyUrl(string $tag, ?string $page = null) : Response
    {
        $page = empty($page) ? null : (int)$page;
        $this->tag->loadByTitle($tag);
        return $this->redirect($this->tag->getUrl($page), Response::HTTP_MOVED_PERMANENTLY);
    }
}
