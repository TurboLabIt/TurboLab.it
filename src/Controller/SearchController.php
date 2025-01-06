<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class SearchController extends BaseController
{
    const string SECTION_SLUG = "cerca";


    #[Route('/' . self::SECTION_SLUG . '/{termToSearch}', requirements: ['termToSearch' => '.*'], name: 'app_search')]
    public function search(string $termToSearch = '') : Response
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

        return
            $this->render('search/serp.html.twig', [
                'metaRobots'            => 'noindex,nofollow',
                'activeMenu'            => null,
                'FrontendHelper'        => $this->frontendHelper,
                'SideArticles'          => $this->factory->createArticleCollection()->loadLatestPublished()->getItems(4),
                'termToSearch'          => $termToSearch
            ]);
    }
}
