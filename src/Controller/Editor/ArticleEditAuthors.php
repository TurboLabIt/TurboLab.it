<?php
namespace App\Controller\Editor;

use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ArticleEditAuthors extends ArticleEditBaseController
{
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/get-authors-modal', name: 'app_article_edit_authors_get-modal', methods: ['GET'])]
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


    #[Route('/ajax/editor/authors/', name: 'app_article_edit_authors_autocomplete', methods: ['GET'])]
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


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/set-authors', name: 'app_article_edit_authors_submit', methods: ['POST'])]
    public function setAuthors(int $articleId) : JsonResponse|Response
    {
        try {

            $arrAuthorIds = $this->request->get('authors') ?? [];
            $this->loadArticleEditor($articleId)->setAuthorsFromIds($arrAuthorIds);
            $this->factory->getEntityManager()->flush();
            return $this->jsonOKResponse("Autori salvati");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
