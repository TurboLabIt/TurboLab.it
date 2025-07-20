<?php
namespace App\Controller\Editor;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ArticleEditTags extends ArticleEditBaseController
{
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/get-tags-modal', name: 'app_article_edit_tags_get-modal', methods: ['GET'])]
    public function getTagsModal(int $articleId) : Response
    {
        try {
            $this->loadArticleEditor($articleId);

            return $this->json([
                "title" => "🏷️ Modifica tag",
                "body" => $this->twig->render('article/editor/tags-modal.html.twig', [
                    "Article"           => $this->articleEditor,
                    "CommonTagGroups"   => $this->factory->createTagCollection()->getCommonGrouped()
                ])
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/tags/', name: 'app_article_edit_tags_autocomplete', methods: ['GET'])]
    public function tagsAutocomplete() : Response
    {
        $this->ajaxOnly();

        try {

            if( empty($this->getUser()) ) {
                throw $this->createAccessDeniedException('Non sei loggato!');
            }

            $tag = $this->request->get('tag');

            return $this->render('article/editor/tags-autocomplete.html.twig', [
                'Tags' => $this->factory->createTagCollection()->loadBySearchTagOrCreate($tag)
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/set-tags', name: 'app_article_edit_tags_submit', methods: ['POST'])]
    public function setTags(int $articleId) : JsonResponse|Response
    {
        try {

            $arrIdsAndTags = $this->request->get('tags') ?? [];
            $this->loadArticleEditor($articleId)->setTagsFromIdsAndTags($arrIdsAndTags);
            $this->factory->getEntityManager()->flush();
            return $this->jsonOKResponse("Tag salvati");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
