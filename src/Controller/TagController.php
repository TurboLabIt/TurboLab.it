<?php
namespace App\Controller;

use App\Service\Cms\Image;
use App\Service\Cms\Paginator;
use App\Service\Cms\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class TagController extends BaseController
{
    public function __construct(protected Tag $tag)
    { }


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{page<0|1>}', name: 'app_tag_page_0-1')]
    public function appTagPage0Or1(string $tagSlugDashId)
    {
        return $this->redirectToRoute('app_tag', ["tagSlugDashId" => $tagSlugDashId], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{page<[1-9]+[0-9]*>}', name: 'app_tag')]
    public function index(string $tagSlugDashId, Request $request, Paginator $paginator, ?int $page = null) : Response
    {
        $tag = $this->tag->loadBySlugDashId($tagSlugDashId);

        $tagRealUrl = $tag->checkRealUrl($tagSlugDashId, $page);
        if( !empty($tagRealUrl) ) {
            return $this->redirect($tagRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        $taggedArticles = $tag->getArticles($page);

        $paginator
            ->setTotalElementsNum( $taggedArticles->countTotalBeforePagination() )
            ->setCurrentPageNum($page)
            ->build('app_tag', ['tagSlugDashId' => $tagSlugDashId]);

        $lastPageNum = $paginator->isPageOutOfRange();
        if( $lastPageNum !== false ) {

            $lastPageNum = in_array($lastPageNum, [0, 1]) ? null : $lastPageNum;
            return $this->redirectToRoute("app_tag", ["tagSlugDashId" => $tagSlugDashId, "page" => $lastPageNum]);
        }

        $tag
            ->setClientIpAddress( $request->getClientIp() )
            ->countOneView();

        return $this->render('tag/index.html.twig', [
            'metaTitle'         => "#" . $tag->getTitle() . ": articoli, guide e news",
            'metaDescription'   => "Articoli, guide, notizie che riguardano: " . $tag->getTitle(),
            'metaCanonicalUrl'  => $tag->getUrl($page),
            'metaOgType'        => 'article',
            'pageImage'         => $tag->getSpotlightOrDefaultUrlFromArticles(Image::SIZE_MAX),
            'Tag'               => $tag,
            'TaggedArticles'    => $taggedArticles,
            'Paginator'         => $paginator
        ]);
    }


    #[Route('/tag/{tag<[^/]+>}/{page<[0-9]*>}', name: 'app_tag_legacy')]
    public function legacyUrl(string $tag, ?string $page = null) : Response
    {
        $page = empty($page) ? null : (int)$page;

        /** @var Tag $tag */
        $tag = $this->tag->loadByTitle($tag);
        return $this->redirect($tag->getUrl($page), Response::HTTP_MOVED_PERMANENTLY);
    }
}
