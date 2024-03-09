<?php
namespace App\Controller;

use App\Service\Cms\Image;
use App\Service\Cms\Article;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;


class ArticleController extends BaseController
{
    public function __construct(
        protected Article $article,
        RequestStack $requestStack, protected TagAwareCacheInterface $cache, protected ParameterBagInterface $parameterBag,
        protected Environment $twig
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}/{articleSlugDashId<[^/]+-[1-9]+[0-9]*>}', name: 'app_article')]
    public function index(string $tagSlugDashId, string $articleSlugDashId) : Response
    {
        $article = $this->article->loadBySlugDashId($articleSlugDashId);

        $articleRealUrl = $article->checkRealUrl($tagSlugDashId, $articleSlugDashId);
        if( !empty($articleRealUrl) ) {
            return $this->redirect($articleRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        $article
            ->setClientIpAddress( $this->request->getClientIp() )
            ->countOneView();

        return $this->render('article/index.html.twig', [
            'metaTitle'         => $article->getTitle(),
            'metaDescription'   => $article->getAbstract(),
            'metaCanonicalUrl'  => $article->getUrl(),
            'metaOgType'        => 'article',
            'metaPageImageUrl'  => $article->getSpotlightOrDefaultUrl(Image::SIZE_MAX),
            'Article'           => $article
        ]);
    }


    #[Route('/{id<[1-9]+[0-9]*>}', name: 'app_article_shorturl')]
    public function shortUrl(int $id) : Response
    {
        $article = $this->article->load($id);
        return $this->redirect($article->getUrl(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
