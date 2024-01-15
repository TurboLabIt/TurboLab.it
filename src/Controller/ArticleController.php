<?php
namespace App\Controller;

use App\ServiceCollection\Cms\ArticleCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class ArticleController extends BaseController
{
    public function __construct(protected ArticleCollection $articleCollection)
    { }


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{articleSlugDashId<[^/]+-[1-9]+[0-9]*>}', name: 'app_article')]
    public function index(string $tagSlugDashId, string $articleSlugDashId) : Response
    {
        $article = $this->articleCollection->loadBySlugDashId($articleSlugDashId);

        $articleRealUrl = $article->checkRealUrl($tagSlugDashId, $articleSlugDashId);
        if( !empty($articleRealUrl) ) {
            return $this->redirect($articleRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        $article->countOneView();

        return $this->render('article/index.html.twig', [
            'Article' => $article
        ]);
    }


    #[Route('/{id<[1-9]+[0-9]*>}', name: 'app_article_shorturl')]
    public function shortUrl(int $id) : Response
    {
        $article = $this->articleCollection->loadById($id);
        return $this->redirect( $article->getUrl(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
