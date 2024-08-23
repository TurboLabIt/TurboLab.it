<?php
namespace App\Controller;

use App\Service\Cms\Image;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;
use App\Service\User;


class AuthorContoller extends BaseController
{
    protected User $mainAuthor;


    #[Route('/utenti/{usernameClean}/{page<0|1>}', name: 'app_author_page_0-1')]
    public function appAuthorPage0Or1(string $usernameClean) : Response
    {
        return $this->redirectToRoute('app_author', ["usernameClean" => $usernameClean], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/utenti/{usernameClean}/{page<[1-9]+[0-9]*>}', name: 'app_author')]
    public function index(string $usernameClean, ?int $page = null) : Response
    {
        $page = empty($page) ? 1 : $page;

        if( !$this->isCachable() ) {

            $buildHtmlResult = $this->buildHtml($usernameClean, $page);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $that = $this;

        $buildHtmlResult =
            $this->cache->get("{$usernameClean}/{$page}", function(CacheItem $cache)
                use($usernameClean, $page, $that) {

                $buildHtmlResult = $this->buildHtml($usernameClean, $page);

                if( is_string($buildHtmlResult) ) {

                    $coldCacheStormBuster = 60 * rand(15, 30); // 30-90 minutes
                    $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY + $coldCacheStormBuster);
                    $cache->tag(["authors", $that->mainAuthor->getCacheKey()]);

                } else {

                    $cache->expiresAfter(-1);
                }

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function buildHtml(string $usernameClean, int $page) : string|Response
    {
        $this->mainAuthor = $user =
            $this->factory->createUser()->loadByusernameClean($usernameClean);

        $authorArticles = $user->getArticles($page);

        try {
            $oPages =
                $this->paginator
                    ->setBaseUrl( $user->getUrl() )
                    ->buildByTotalItems($page, $authorArticles->countTotalBeforePagination() );

        } catch(PaginatorOverflowException $ex) {

            $lastPageUrl = $user->getUrl( $ex->getMaxPage() );
            return $this->redirect($lastPageUrl);
        }

        return
            $this->twig->render('user/author.html.twig', [
                'metaTitle'         => "Articoli, guide e news a cura di " . 
                                            $user->getFullName() . ( $page < 2 ? '' : " - Pagina $page"),
                'metaCanonicalUrl'  => $user->getUrl($page),
                'metaPageImageUrl'  => $user->getAvatarUrl(Image::SIZE_MAX),
                'activeMenu'        => 'null',
                'FrontendHelper'    => $this->frontendHelper,
                'Author'            => $user,
                'Articles'          => $authorArticles,
                'Pages'             => $authorArticles->count() > 0 ? $oPages : null,
                'currentPage'       => $page
            ]);
        }
}