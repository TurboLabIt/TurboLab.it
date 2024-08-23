<?php
namespace App\Controller;

use App\Service\Cms\Image;
use App\Service\Cms\Tag;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;


class TagController extends BaseController
{
    const int CACHE_DEFAULT_EXPIRY = 60 * 240; // 4 hours

    protected Tag $mainTag;


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{page<0|1>}', name: 'app_tag_page_0-1')]
    public function appTagPage0Or1(string $tagSlugDashId) : Response
    {
        return $this->redirectToRoute('app_tag', ["tagSlugDashId" => $tagSlugDashId], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{page<[1-9]+[0-9]*>}', name: 'app_tag')]
    public function index(string $tagSlugDashId, ?int $page = null) : Response
    {
        $page = empty($page) ? 1 : $page;

        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml($tagSlugDashId, $page);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $that = $this;

        $buildHtmlResult =
            $this->cache->get("{$tagSlugDashId}/{$page}", function(CacheItem $cache)
            use($tagSlugDashId, $page, $that) {

                $buildHtmlResult = $this->buildHtml($tagSlugDashId, $page);

                if( is_string($buildHtmlResult) ) {

                    $coldCacheStormBuster = 60 * rand(30, 90); // 30-90 minutes
                    $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY + $coldCacheStormBuster);
                    $cache->tag(["tags", $that->mainTag->getCacheKey()]);

                } else {

                    $cache->expiresAfter(-1);
                }

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function buildHtml(string $tagSlugDashId, int $page) : string|Response
    {
        $this->mainTag = $tag =
            $this->factory->createTag()->loadBySlugDashId($tagSlugDashId);

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

        // TODO handle visitor counter
        $tag
            ->setClientIpAddress( $this->request->getClientIp() )
            ->countOneView();

        return
            $this->twig->render('tag/index.html.twig', [
                'metaTitle'         => $tag->getTitleFormatted() . 
                                            ": articoli, guide e news" . ( $page < 2 ? '' : " - Pagina $page"),
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
