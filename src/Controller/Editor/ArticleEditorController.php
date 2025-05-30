<?php
namespace App\Controller\Editor;

use App\Controller\BaseController;
use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ArticleEditorController extends BaseController
{
    public function __construct(protected ArticleEditor $articleEditor, protected Article $article, RequestStack $requestStack)
        { $this->request = $requestStack->getCurrentRequest(); }


    #[Route('/scrivi', name: 'app_editor_new')]
    public function new() : JsonResponse
    {
        return new JsonResponse("Pagina in aggiornamento", Response::HTTP_SERVICE_UNAVAILABLE);
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
