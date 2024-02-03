<?php
namespace App\Controller;

use App\Service\Cms\Article;
use App\Service\Cms\HtmlProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class ArticleController extends BaseController
{
    public function __construct(protected Article $article)
    {}


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{articleSlugDashId<[^/]+-[1-9]+[0-9]*>}', name: 'app_article')]
    public function index(string $tagSlugDashId, string $articleSlugDashId, HtmlProcessor $htmlProcessor, Request $request) : Response
    {
        $article = $this->article->loadBySlugDashId($articleSlugDashId);

        $articleRealUrl = $article->checkRealUrl($tagSlugDashId, $articleSlugDashId);
        if( !empty($articleRealUrl) ) {
            return $this->redirect($articleRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        $article
            ->setClientIpAddress( $request->getClientIp() )
            ->countOneView()
            ->setHtmlProcessor($htmlProcessor);

        return $this->render('article/index.html.twig', [
            'Article' => $article
        ]);
    }


    #[Route('/{id<[1-9]+[0-9]*>}', name: 'app_article_shorturl')]
    public function shortUrl(int $id) : Response
    {
        $article = $this->article->load($id);
        return $this->redirect($article->getUrl(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
