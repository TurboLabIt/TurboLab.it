<?php
namespace App\Controller;

use App\Service\Cms\Image;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use TurboLabIt\PaginatorBundle\Exception\PaginatorOverflowException;


class AuthorContoller extends BaseController
{
    #[Route('/utenti/{usernameClean}/{page<0|1>}', name: 'app_author_page_0-1')]
    public function appAuthorPage0Or1(string $usernameClean) : Response
    {
        return $this->redirectToRoute('app_author', ["usernameClean" => $usernameClean], Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/utenti/{usernameClean}/{page<[1-9]+[0-9]*>}', name: 'app_author')]
    public function index(string $usernameClean, ?int $page = null) : Response
    {
        $user = $this->factory->createUser()->loadByusernameClean($usernameClean);

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

        return $this->render('user/author.html.twig', [
            'metaTitle'         => "Articoli, guide e news a cura di " . $user->getFullName() . ( $page < 2 ? '' : " - Pagina $page"),
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
