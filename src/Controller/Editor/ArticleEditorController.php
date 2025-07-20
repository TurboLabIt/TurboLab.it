<?php
namespace App\Controller\Editor;

use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ArticleEditorController extends ArticleEditBaseController
{
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}', name: 'app_editor_article_update', methods: ['POST'])]
    public function update(int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            foreach(['title', 'body'] as $param) {

                $value  = $this->request->get($param);
                $method = "set" . ucfirst($param);
                $this->articleEditor->$method($value);
            }

            $this->articleEditor->save();
            return $this->jsonOKResponse("Articolo salvato");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
