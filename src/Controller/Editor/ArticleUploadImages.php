<?php
namespace App\Controller\Editor;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ArticleUploadImages extends ArticleEditBaseController
{
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/images/upload', name: 'app_article_edit_images-upload', methods: ['POST'])]
    public function upload(int $articleId) : JsonResponse|Response
    {
        try {
            dd("TODO");
            $arrIdsAndTags = $this->request->get('tags') ?? [];
            $this->loadArticleEditor($articleId)->setTagsFromIdsAndTags($arrIdsAndTags);
            $this->factory->getEntityManager()->flush();
            return $this->jsonOKResponse("Tag salvati");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
