<?php
namespace App\Controller;

use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class FeedController extends BaseController
{
    const string SECTION_SLUG = "feed";


    #[Route('/' . self::SECTION_SLUG, name: 'app_feed')]
    public function main() : Response
    {
        return
            $this->sendRssResponse("app_feed", [
                "title"         => "TurboLab.it | Feed Principale",
                "description"   => "Questo feed eroga gli articoli e le news più recenti"
            ], 'loadLatestPublished');
    }


    #[Route('/' . self::SECTION_SLUG . '/fullfeed', name: 'app_feed_fullfeed')]
    public function fullFeed(): Response
    {
        return
            $this->sendRssResponse("app_feed_fullfeed", [
                "title"         => "TurboLab.it | Full Feed",
                "description"   => "Questo feed eroga gli articoli e le news più recenti, in forma completa",
                "fullFeed"      => true
            ], 'loadLatestPublished');
    }


    #[Route('/' . self::SECTION_SLUG . '/nuovi-finiti', name: 'app_feed_new_unpublished')]
    public function newUnpublished(): Response
    {
        $this->cacheIsDisabled = true;
        return
            $this->sendRssResponse("app_feed_new_unpublished", [
                "title"         => "TurboLab.it | Nuovi contenuti completati, in attesa di pubblicazione",
                "description"   => "Questo feed eroga i contenuti che gli autori hanno indicato come completati, ma che non sono ancora stati pubblicati"
            ], 'loadLatestReadyForReview');
    }


    protected function sendRssResponse(string $routeName, array $arrData, callable|string $fxLoadArticle) : Response
    {
        if( !array_key_exists('selfUrl', $arrData) ) {
            $arrData["selfUrl"] = strtok($this->request->getUri(), '?');
        }

        if( !array_key_exists('fullFeed', $arrData) ) {
            $arrData["fullFeed"] = false;
        }

        if( !$this->isCachable() ) {

            $txtResponseBody =
                $this->twig->render('feed/rss.xml.twig', array_merge($arrData, [
                    "activeMenu"    => "feed",
                    "Articles"      => $this->factory->createArticleCollection()->$fxLoadArticle()
                ]));

        } else {


            $cacheKey = "feed_{$routeName}_" . md5(serialize($arrData));

            $txtResponseBody =
                $this->cache->get($cacheKey, function(CacheItem $cache) use($routeName, $arrData, $fxLoadArticle) {

                    $txtResponseBody =
                        $this->twig->render('feed/rss.xml.twig', array_merge($arrData, [
                            "activeMenu"    => "feed",
                            "Articles"      => $this->factory->createArticleCollection()->$fxLoadArticle()
                        ]));

                    $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY);
                    $cache->tag([$routeName, "app_feed"]);

                    return $txtResponseBody;
                });
        }

        $response = new Response($txtResponseBody);

        /**
         * 📚 https://validator.w3.org/feed/docs/warning/UnexpectedContentType.html
         * RSS feeds should be served as application/rss+xml. Alternatively,
         * for compatibility with widely-deployed web browsers, [...] application/xml
         */
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }
}
