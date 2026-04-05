<?php
namespace App\Controller;

use App\Exception\AjaxOnlyException;
use Exception;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


/**
 * 📚 https://support.google.com/programmable-search/answer/4513751
 */
class SearchController extends BaseController
{
    const int CACHE_DEFAULT_EXPIRY  = 60 * 90; // 90 minutes
    const string SECTION_SLUG       = "cerca";


    #[Route('/' . self::SECTION_SLUG . '/{termToSearch}', requirements: ['termToSearch' => '.*'], name: 'app_search')]
    public function search(string $termToSearch = '') : Response
    {
        // legacy redirect
        $legacyQueryStringParam = $this->request->get('query') ?? '';
        $legacyQueryStringParam = trim($legacyQueryStringParam);

        if( !empty($legacyQueryStringParam) ){
            return $this->redirectToRoute('app_search', ['termToSearch' => $legacyQueryStringParam], Response::HTTP_MOVED_PERMANENTLY);
        }

        $cleanTermToSearch = htmlspecialchars($termToSearch, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');

        return
            $this->render('search/serp.html.twig', [
                'metaTitle'         => empty($cleanTermToSearch) ? "Cerca su TurboLab.it" : "Risultati della ricerca per: $cleanTermToSearch",
                'metaRobots'        => 'noindex,follow',
                'activeMenu'        => static::SECTION_SLUG,
                'FrontendHelper'    => $this->frontendHelper,
                'termToSearch'      => $cleanTermToSearch,
            ]);
    }


    #[Route('/' . self::SECTION_SLUG . '/ajax/serp/{termToSearch}', requirements: ['termToSearch' => '.*'], name: 'app_search_ajax', priority: 1)]
    public function performSearch(string $termToSearch = '') : Response
    {
        try {
            $this->ajaxOnly();

        } catch(AjaxOnlyException){

            return $this->redirectToRoute('app_search', ['termToSearch' => $termToSearch]);
        }


        try {

            if( !$this->isCachable() ) {

                $buildHtmlResult = $this->buildHtml($termToSearch, 'search/results.html.twig');

            } else {

                $buildHtmlResult =
                    $format = $this->request->query->getInt('format');
                    $cacheKey = "search_{$termToSearch}_f{$format}";
                    $this->cache->get($cacheKey, function(ItemInterface $cacheItem) use($termToSearch) {

                        $buildHtmlResult = $this->buildHtml($termToSearch, 'search/results.html.twig');

                        $cacheItem->expiresAfter(static::CACHE_DEFAULT_EXPIRY);
                        $cacheItem->tag(["search"]);

                        return $buildHtmlResult;
                    });
            }

        } catch(Exception $ex) {

            return $this->textErrorResponse($ex);
        }

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function buildHtml(string $termToSearch, string $twigTemplatePath) : string|Response
    {
        $format = $this->request->query->getInt('format') ?: null;

        return
            $this->twig->render($twigTemplatePath, [
                'LocalResults' => $this->factory->createArticleCollection()->loadSerp($termToSearch, $format)
            ]);
    }


    #[Route('/' . self::SECTION_SLUG . '/ajax/forum/{termToSearch}', requirements: ['termToSearch' => '.*'], name: 'app_search_ajax_forum', priority: 1)]
    public function performSearchForForum(string $termToSearch = '') : Response
    {
        $this->ajaxOnly();

        try {

            $buildHtmlResult = $this->buildHtml($termToSearch, 'search/results-forum.html.twig');

        } catch(Exception $ex) {

            return $this->textErrorResponse($ex);
        }

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    #[Route('/' . self::SECTION_SLUG . '/ajax/link-article/{termToSearch}', requirements: ['termToSearch' => '.*'], name: 'app_search_ajax_link-article', priority: 1)]
    public function performSearchForLinkArticle(string $termToSearch = '') : Response
    {
        $this->ajaxOnly();

        try {

            $format     = $this->request->query->getInt('format') ?: null;
            $authorId   = $this->request->query->getBoolean('only-mine') && $this->getUser()
                            ? $this->getUser()->getId() : null;

            $buildHtmlResult =
                $this->twig->render('search/results-link-article.html.twig', [
                    'LocalResults' => $this->factory->createArticleCollection()->loadSerp($termToSearch, $format, $authorId)
                ]);

        } catch(Exception $ex) {

            return $this->textErrorResponse($ex);
        }

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }
}
