<?php
namespace App\Controller;

use App\Service\Cms\Image;
use App\Service\Cms\Tag;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;


class TagController extends BaseController
{
    const int CACHE_DEFAULT_EXPIRY = 60 * 240; // 4 hours

    protected Tag $mainTag;


    #[Route('/{tagSlugDashId<[^/]*-[1-9]+[0-9]*>}/{page<0|1>}', name: 'app_tag_page_0-1')]
    public function appTagPage0Or1(string $tagSlugDashId) : Response
    {
        return $this->redirectToRoute('app_tag', ["tagSlugDashId" => $tagSlugDashId], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/{tagSlugDashId<[^/]*-[1-9]+[0-9]*>}/{page<[1-9]+[0-9]*>}', name: 'app_tag')]
    public function index(string $tagSlugDashId, ?int $page = null) : Response
    {
        $page = empty($page) ? 1 : $page;

        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml($tagSlugDashId, $page);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $buildHtmlResult =
            $this->cache->get("$tagSlugDashId/$page", function(ItemInterface $cacheItem) use($tagSlugDashId, $page) {

                $buildHtmlResult = $this->buildHtml($tagSlugDashId, $page);

                if( is_string($buildHtmlResult) ) {

                    $cacheItem->expiresAfter(static::CACHE_DEFAULT_EXPIRY);
                    $cacheItem->tag(["tags", $this->mainTag->getCacheKey()]);

                } else {

                    $cacheItem->expiresAfter(-1);
                }

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function buildHtml(string $tagSlugDashId, int $page) : string|Response
    {
        $tag = $this->factory->createTag()->loadBySlugDashId($tagSlugDashId);
        $replacementUrl = $tag->getReplacement()?->getUrl();

        if( !empty($replacementUrl) ) {
            return $this->redirect($replacementUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        $this->mainTag = $tag;

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

        $metaTitle  = $tag->getTitleForHTMLAttribute() . ': articoli, guide e news';
        $metaTitle .= $page < 2 ? '' : " - pagina $page";

        return
            $this->twig->render('tag/index.html.twig', [
                'cmsId'             => $tag->getId(),
                'cmsType'           => $tag->getClass(),
                'metaTitle'         => $metaTitle,
                'metaDescription'   => $metaTitle,
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
        $page       = empty($page) ? null : (int)$page;
        $tag        = $this->factory->createTag()->loadByTitle($tag);
        $redirectUrl= $tag->getReplacement()?->getUrl() ?? $tag->getUrl($page);
        return $this->redirect($redirectUrl, Response::HTTP_MOVED_PERMANENTLY);
    }
}
