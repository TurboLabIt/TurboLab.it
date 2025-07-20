<?php
namespace App\Controller\Editor;

use App\Controller\BaseController;
use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ArticleEditorController extends BaseController
{
    //<editor-fold defaultstate="collapsed" desc="*** 📜 Title and Body ***">
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
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 👥 Authors ***">
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/get-authors-modal', name: 'app_editor_article_get-authors-modal', methods: ['GET'])]
    public function getAuthorsModal(int $articleId) : Response
    {
        try {
            $this->loadArticleEditor($articleId);

            return $this->json([
                "title" => "👥 Modifica autori",
                "body" => $this->twig->render('article/editor/authors-modal.html.twig', [
                    "Article"       => $this->articleEditor,
                    "LatestAuthors" => $this->factory->createUserCollection()->loadLatestAuthors()
                ])
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/authors/', name: 'app_editor_article_authors-autocomplete', methods: ['GET'])]
    public function authorsAutocomplete() : Response
    {
        $this->ajaxOnly();

        try {

            if( empty($this->getUser()) ) {
                throw $this->createAccessDeniedException('Non sei loggato!');
            }

            $username = $this->request->get('username');

            $authors = $this->factory->createUserCollection();

            empty($username) ? $authors->loadLatestAuthors() : $authors->loadBySearchUsername($username);

            return $this->render('article/editor/authors-autocomplete.html.twig', [
                'Authors' => $authors
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/set-authors', name: 'app_editor_article_set-authors', methods: ['POST'])]
    public function setAuthors(int $articleId) : JsonResponse|Response
    {
        try {

            $arrAuthorIds = $this->request->get('authors') ?? [];
            $this->loadArticleEditor($articleId)->setAuthorsFromIds($arrAuthorIds);
            $this->factory->getEntityManager()->flush();
            return $this->jsonOKResponse("Autori salvati");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🏷️ Tags ***">
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/get-tags-modal', name: 'app_editor_article_get-tags-modal', methods: ['GET'])]
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

    #[Route('/ajax/editor/tags/', name: 'app_editor_article_tags-autocomplete', methods: ['GET'])]
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


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/set-tags', name: 'app_editor_article_set-tags', methods: ['POST'])]
    public function setTags(int $articleId) : JsonResponse|Response
    {
        try {

            $arrIdsAndTags = $this->request->get('tags') ?? [];
            $this->loadArticleEditor($articleId)->setTagsFromIdsAndTags($arrIdsAndTags);
            $this->factory->getEntityManager()->flush();
            return $this->jsonOKResponse("Tag salvati");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
    //</editor-fold>
}
