<?php
namespace App\Controller\Editor;

use App\Controller\BaseController;
use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Service\FrontendHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ArticleEditorController extends BaseController
{
    public function __construct(
        protected Factory $factory,
        protected ArticleEditor $articleEditor, protected Article $article, RequestStack $requestStack,
        protected FrontendHelper $frontendHelper
    )
        { $this->request = $requestStack->getCurrentRequest(); }


    #[Route('/scrivi', name: 'app_editor_new')]
    public function new() : Response
    {
        $currentUser = $this->factory->getCurrentUser();

        return $this->render('article/editor/new.html.twig', [
            'metaTitle'                     => 'Scrivi nuovo articolo',
            'metaCanonicalUrl'              => $this->generateUrl('app_editor_new', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'activeMenu'                    => '',
            'FrontendHelper'                => $this->frontendHelper,
            'currentUserUrl'                => $currentUser?->getUrl(),
            'currentUserArticleNum'         => $currentUser?->getArticlesNum(),
            'CurrentUserDraftArticles'      => $currentUser?->getArticlesDraft(),
            'CurrentUserInReviewArticles'   => $currentUser?->getArticlesInReview(),
            'CurrentUserUpcomingArticles'   => $currentUser?->getArticlesUpcoming(),
            'CurrentUserKoArticles'         => $currentUser?->getArticlesKo(),
            'SideArticles'                  => []
        ]);
    }


    #[Route('/editor/article/body/{articleId<[1-9]+[0-9]*>}', name: 'app_editor_article_body')]
    public function body(int $articleId) : JsonResponse
    {
        $this->articleEditor->load($articleId);

        $html = $this->articleEditor->getBodyForDisplay();

        $this->articleEditor->load($articleId)
            ->setBody($html)
            ->save();

        $this->article->load($articleId);

        return $this->json([
            "result"    => "OK",
            "body"      => $this->article->getBodyForDisplay()
        ]);
    }
}
