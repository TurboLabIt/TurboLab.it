<?php
namespace App\Controller;

use App\Service\Cms\HtmlProcessor;
use App\ServiceCollection\Cms\ArticleCollection;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;


class FeedController extends BaseController
{
    const bool DISPLAY_AS_HTML = false;

    protected ?HtmlProcessor $htmlProcessor = null;


    public function __construct(
        protected ArticleCollection $articleCollection,
        RequestStack $requestStack, protected TagAwareCacheInterface $cache, protected ParameterBagInterface $parameterBag,
        protected Environment $twig
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }


    #[Route('/feed', name: 'app_feed')]
    public function main() : Response
    {
        $arrData = [
            "title"         => "TurboLab.it | Feed Principale",
            "description"   => "Questo feed eroga articoli più recenti pubblicati in home page"
        ];

        return $this->sendRssResponse( "app_feed", $arrData, $this->articleCollection->loadLatestPublished(...) );
    }


    #[Route('/feed/fullfeed', name: 'app_feed_fullfeed')]
    public function fullFeed(HtmlProcessor $htmlProcessor): Response
    {
        $arrData = [
            "title"         => "TurboLab.it | Full Feed",
            "description"   => "Questo feed eroga i nuovi articoli in forma completa",
            "fullFeed"      => true,
        ];

        $this->htmlProcessor = $htmlProcessor;

        return $this->sendRssResponse( "app_feed_fullfeed", $arrData, $this->articleCollection->loadLatestPublished(...) );
    }


    #[Route('/feed/nuovi-finiti', name: 'app_feed_new_unpublished')]
    public function newUnpublished(): Response
    {
        $this->cacheIsDisabled = true;

        $arrData    = [
            "title"         => "TurboLab.it | Nuovi contenuti completati, in attesa di pubblicazione",
            "description"   => "Questo feed eroga i contenuti che gli autori hanno indicato come completati, ma che non sono ancora stati pubblicati",
        ];

        return $this->sendRssResponse( "app_feed_new_unpublished", $arrData, $this->articleCollection->loadLatestReadyForReview(...) );
    }


    protected function sendRssResponse(string $routeName, array $arrData, callable $fxLoadArticle) : Response
    {
        if( !array_key_exists('selfUrl', $arrData) ) {
            $arrData["selfUrl"] = strtok($this->request->getUri(), '?');
        }

        if( !array_key_exists('fullFeed', $arrData) ) {
            $arrData["fullFeed"] = false;
        }

        if( !$this->isCachable() ) {

            $fxLoadArticle();

            if( !empty($this->htmlProcessor) ) {
                $this->articleCollection->setHtmlProcessor($this->htmlProcessor);
            }

            $buildXmlResult = $this->buildXml($arrData);

        } else {

            $buildXmlResult =
                $this->cache->get($routeName, function(CacheItem $cache) use($routeName, $arrData, $fxLoadArticle) {

                    $cache->tag([$routeName, "app_feed", "app_home"]);
                    $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY);

                    $fxLoadArticle();

                    if( !empty($this->htmlProcessor) ) {
                        $this->articleCollection->setHtmlProcessor($this->htmlProcessor);
                    }

                    return $this->buildXml($arrData);
                });
        }

        $response = new Response($buildXmlResult);

        if( !static::DISPLAY_AS_HTML) {
            /**
             * 📚 https://validator.w3.org/feed/docs/warning/UnexpectedContentType.html
             * RSS feeds should be served as application/rss+xml. Alternatively,
             * for compatibility with widely-deployed web browsers, [...] application/xml
             */
            $response->headers->set('Content-Type', 'application/xml');
        }

        return $response;
    }


    protected function buildXml(array $arrFeedData) : string
    {
        $twigFile = static::DISPLAY_AS_HTML ? 'feed/rss.html.twig' : 'feed/rss.xml.twig';
        $xml = $this->twig->render($twigFile, array_merge($arrFeedData, [
            "Articles"  => $this->articleCollection
        ]));

        return $xml;
    }
}
