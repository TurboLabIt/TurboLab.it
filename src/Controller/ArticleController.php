<?php
namespace App\Controller;

use App\Service\Cms\Article;
use App\Service\Cms\Image;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;


class ArticleController extends BaseController
{
    protected Article $mainArticle;


    #[Route('/{tagSlugDashId<[^/]*-[1-9]+[0-9]*>}/{articleSlugDashId<[^/]*-[1-9]+[0-9]*>}', name: 'app_article')]
    public function index(string $tagSlugDashId, string $articleSlugDashId) : Response
    {
        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml($tagSlugDashId, $articleSlugDashId);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $buildHtmlResult =
            $this->cache->get("$tagSlugDashId/$articleSlugDashId", function(ItemInterface $cacheItem) use($tagSlugDashId, $articleSlugDashId) {

                $buildHtmlResult = $this->buildHtml($tagSlugDashId, $articleSlugDashId);

                if( is_string($buildHtmlResult) ) {

                    $coldCacheStormBuster = 60 * rand(120, 240); // 2-4 hours
                    $cacheItem->expiresAfter(static::CACHE_DEFAULT_EXPIRY + $coldCacheStormBuster);
                    $cacheItem->tag(["articles", $this->mainArticle->getCacheKey()]);

                } else {

                    $cacheItem->expiresAfter(-1);
                }

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function isCachable() : bool
    {
        if( !empty($this->getUser()) ) {
            return false;
        }

        return parent::isCachable();
    }


    protected function buildHtml(string $tagSlugDashId, string $articleSlugDashId) : string|Response
    {
        $this->mainArticle = $article = $this->factory->createArticle()->loadBySlugDashIdComplete($articleSlugDashId);

        $articleRealUrl = $article->checkRealUrl($tagSlugDashId, $articleSlugDashId);
        if( !empty($articleRealUrl) ) {
            return $this->redirect($articleRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        // TODO handle visitor counter
        $article
            ->setClientIpAddress( $this->request->getClientIp() )
            ->countOneView();

        $articleHowTo = $article->isEditable() ? $this->factory->createArticle()->load(Article::ID_PUBLISH_ARTICLE) : null;

        $html =
            $this->twig->render('article/index.html.twig', [
                'Sentinel'              => $this->factory->createArticleSentinel($article),
                'metaTitle'             => $article->getTitleForHTMLAttribute(),
                'metaDescription'       => $article->getAbstractForHTMLAttribute(),
                'metaCanonicalUrl'      => $article->getUrl(),
                'metaOgType'            => 'article',
                'metaPageImageUrl'      => $article->getSpotlightOrDefaultUrl(Image::SIZE_MAX),
                'metaRobots'            => $article->getMetaRobots(),
                'activeMenu'            => $article->getActiveMenu(),
                'FrontendHelper'        => $this->frontendHelper,
                'CurrentUser'           => $this->getCurrentUser(),
                'ArticleFormats'        => [
                    Article::FORMAT_ARTICLE => 'Articolo, guida, recensione',
                    Article::FORMAT_NEWS    => 'Notizia, segnalazione'
                ],
                'Article'               => $article,
                'ArticleHowTo'          => $articleHowTo,
                'BitTorrentGuide'       => $this->factory->createArticle()->load(Article::ID_BITTORRENT_GUIDE),
                'commentsLoadingUrl'    => $article->getCommentsAjaxLoadingUrl(),
                'SideArticles'          => $this->factory->createArticleCollection()->loadSideBarOf($article)
            ]);

        if( $article->isDraft() ) {
            // return "503, maintenance" + prevent caching
            return new Response($html, Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => 3600 * 24]);
        }

        if( $article->isKo() ) {
            // return "410, gone" + prevent caching
            return new Response($html, Response::HTTP_GONE);
        }

        return $html;
    }


    #[Route('/{id<[1-9]+[0-9]*>}', name: 'app_article_shorturl')]
    public function shortUrl(int $id) : Response
    {
        $article = $this->factory->createArticle()->load($id);
        return $this->redirect($article->getUrl(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
