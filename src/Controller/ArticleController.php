<?php
namespace App\Controller;

use App\Service\Cms\Article;
use App\Service\Cms\Image;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class ArticleController extends BaseController
{
    protected Article $mainArticle;


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{articleSlugDashId<[^/]+-[1-9]+[0-9]*>}', name: 'app_article')]
    public function index(string $tagSlugDashId, string $articleSlugDashId) : Response
    {
        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml($tagSlugDashId, $articleSlugDashId);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $that = $this;

        $buildHtmlResult =
            $this->cache->get("{$tagSlugDashId}/{$articleSlugDashId}", function(CacheItem $cache)
                use($tagSlugDashId, $articleSlugDashId, $that) {

                $buildHtmlResult = $this->buildHtml($tagSlugDashId, $articleSlugDashId);

                if( is_string($buildHtmlResult) ) {

                    $coldCacheStormBuster = 60 * rand(120, 240); // 2-4 hours
                    $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY + $coldCacheStormBuster);
                    $cache->tag(["articles", $that->mainArticle->getCacheKey()]);

                } else {

                    $cache->expiresAfter(-1);
                }

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function buildHtml(string $tagSlugDashId, string $articleSlugDashId) : string|Response
    {
        $this->mainArticle = $article = 
            $this->factory->createArticle()->loadBySlugDashIdComplete($articleSlugDashId);

        $articleRealUrl = $article->checkRealUrl($tagSlugDashId, $articleSlugDashId);
        if( !empty($articleRealUrl) ) {
            return $this->redirect($articleRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        // TODO handle visitor counter
        $article
            ->setClientIpAddress( $this->request->getClientIp() )
            ->countOneView();

        return
            $this->twig->render('article/index.html.twig', [
                'metaTitle'             => $article->getTitle(),
                'metaDescription'       => $article->getAbstract(),
                'metaCanonicalUrl'      => $article->getUrl(),
                'metaOgType'            => 'article',
                'metaPageImageUrl'      => $article->getSpotlightOrDefaultUrl(Image::SIZE_MAX),
                'activeMenu'            => $article->getActiveMenu(),
                'FrontendHelper'        => $this->frontendHelper,
                'Article'               => $article,
                'BitTorrentGuide'       => $this->factory->createArticle()->load(Article::ID_BITTORRENT_GUIDE),
                'commentsLoadingUrl'    => $article->getCommentsAjaxLoadingUrl(),
                'SideArticles'          => $this->factory->createArticleCollection()
                                                ->loadSideBarOf($article)
            ]);
    }


    #[Route('/{id<[1-9]+[0-9]*>}', name: 'app_article_shorturl')]
    public function shortUrl(int $id) : Response
    {
        $article = $this->factory->createArticle()->load($id);
        return $this->redirect($article->getUrl(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
