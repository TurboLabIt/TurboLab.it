<?php
namespace App\Controller\Editor;

use App\Service\Mailer;
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
            $this->loginRequired();

            $username   = $this->request->get('username');
            $authors    = $this->factory->createUserCollection();

            empty($username) ? $authors->loadLatestAuthors() : $authors->loadBySearchUsername($username);

            return $this->render('article/editor/authors-autocomplete.html.twig', [
                'Authors' => $authors
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/set-authors', name: 'app_article_edit_authors_submit', methods: ['POST'])]
    public function setAuthors(int $articleId, Mailer $mailer) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $arrPreviousAuthors = $this->articleEditor->getAuthors();
            $arrAuthorIds       = $this->request->get('authors') ?? [];
            $authors            = $this->factory->createUserCollection()->load($arrAuthorIds);

            $currentUserAsAuthor = $this->sentinel->getCurrentUserAsAuthor();

            $this->articleEditor
                ->setAuthors($authors, $currentUserAsAuthor)
                ->save();

            $mailer->buildArticleChangeAuthors($this->articleEditor, $currentUserAsAuthor, $arrPreviousAuthors);

            $jsonOkMessage = "Autori salvati";

            return
                $this
                    ->clearCachedArticle(null, null, $arrPreviousAuthors)
                    ->handleNotification($mailer, $jsonOkMessage)
                    ->jsonOKResponse($jsonOkMessage);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
