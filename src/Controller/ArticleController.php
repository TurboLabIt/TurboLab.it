<?php
namespace App\Controller;

use App\Service\Cms\Image;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class ArticleController extends BaseController
{
    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{articleSlugDashId<[^/]+-[1-9]+[0-9]*>}', name: 'app_article')]
    public function index(string $tagSlugDashId, string $articleSlugDashId) : Response
    {
        $article = $this->factory->createArticle()->loadBySlugDashIdComplete($articleSlugDashId);

        $articleRealUrl = $article->checkRealUrl($tagSlugDashId, $articleSlugDashId);
        if( !empty($articleRealUrl) ) {
            return $this->redirect($articleRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        $article
            ->setClientIpAddress( $this->request->getClientIp() )
            ->countOneView();

        return $this->render('article/index.html.twig', [
            'metaTitle'             => $article->getTitle(),
            'metaDescription'       => $article->getAbstract(),
            'metaCanonicalUrl'      => $article->getUrl(),
            'metaOgType'            => 'article',
            'metaPageImageUrl'      => $article->getSpotlightOrDefaultUrl(Image::SIZE_MAX),
            'activeMenu'            => $article->getActiveMenu(),
            'FrontendHelper'        => $this->frontendHelper,
            'Article'               => $article,
            'commentsLoadingUrl'    => $article->getCommentsAjaxLoadingUrl(),
            'SideArticles'          => $this->factory->createArticleCollection()
                                            ->loadSideBarOf($article)
        ]);
    }


    #[Route('/{id<[1-9]+[0-9]*>}', name: 'app_article_shorturl')]
    public function shortUrl(int $id) : Response
    {
        $article = $this->factory->createArticle()->load($id);
        return $this->redirect($article->getUrl(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
