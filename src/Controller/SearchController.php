<?php
namespace App\Controller;

use App\Service\GoogleProgrammableSearchEngine;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


/**
 * 📚 https://support.google.com/programmable-search/answer/4513751
 */
class SearchController extends BaseController
{
    const string SECTION_SLUG       = "cerca";
    const string NO_RESULTS_MESSAGE  = "Nessun risultato.";


    #[Route('/' . self::SECTION_SLUG . '/{termToSearch}', requirements: ['termToSearch' => '.*'], name: 'app_search')]
    public function search(GoogleProgrammableSearchEngine $searchEngine, string $termToSearch = '') : Response
    {
        // legacy redirect
        $legacyQueryStringParam = $this->request->get('query') ?? '';
        $legacyQueryStringParam = trim($legacyQueryStringParam);

        if( !empty($legacyQueryStringParam) ){
            return $this->redirectToRoute('app_search', ['termToSearch' => $legacyQueryStringParam], Response::HTTP_MOVED_PERMANENTLY);
        }

        $trimmedTermToSearch = trim($termToSearch);

        if( empty($trimmedTermToSearch) ) {
            return $this->redirectToRoute('app_home', [],Response::HTTP_MOVED_PERMANENTLY);
        }

        if( $termToSearch != $trimmedTermToSearch ){
            return $this->redirectToRoute('app_search', ['termToSearch' => $trimmedTermToSearch], Response::HTTP_MOVED_PERMANENTLY);
        }


        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml($searchEngine, $trimmedTermToSearch);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $buildHtmlResult =
            $this->cache->get("search_$trimmedTermToSearch", function(CacheItem $cache)
            use($searchEngine, $trimmedTermToSearch) {

                $buildHtmlResult = $this->buildHtml($searchEngine, $trimmedTermToSearch);

                $coldCacheStormBuster = 60 * rand(120, 240); // 2-4 hours
                $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY + $coldCacheStormBuster);
                $cache->tag(["search"]);

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function buildHtml(GoogleProgrammableSearchEngine $searchEngine, string $termToSearch) : string|Response
    {
        return
            $this->twig->render('search/serp.html.twig', [
                'metaRobots'        => 'noindex,nofollow',
                'activeMenu'        => null,
                'FrontendHelper'    => $this->frontendHelper,
                'SideArticles'      => $this->factory->createArticleCollection()->loadLatestPublished()->getItems(4),
                'termToSearch'      => $termToSearch,
                'GoogleResults'     => $searchEngine->query($termToSearch),
                'LocalResults'      => $this->factory->createArticleCollection()->loadSerp($termToSearch),
                'noResultsMessage'  => static::NO_RESULTS_MESSAGE
            ]);
    }
}
