<?php
namespace App\Controller\Editor;

use App\Service\Cms\ArticleEditor;
use App\Service\Cms\ArticlePlanner;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;


class ArticleEditorController extends ArticleEditBaseController
{
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/update/', name: 'app_editor_article_update', methods: ['POST'])]
    public function update(int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            foreach(['title', 'body'] as $param) {

                $value = $this->request->get($param);
                $method = "set" . ucfirst($param);
                $this->articleEditor->$method($value);
            }

            $this->articleEditor->save();

            $this->clearCachedArticle();

            return $this->jsonOKResponse("Articolo salvato");

        } catch(UniqueConstraintViolationException $ex) {

            if ($ex->getCode() != 1062 || stripos($ex, 'Duplicate entry') === false) {
                throw $ex;
            }

            $title = $this->articleEditor->getTitle();
            $originalArticleUrl = $this->factory->createArticle()->loadByTitle($title)->getShortUrl();

            return
                $this->textErrorResponse(
                    new ConflictHttpException(trim('
                        Impossibile salvare:
                        <a href="' . $originalArticleUrl . '"  target="_blank">esiste già un articolo con questo titolo</a>.
                        Per favore, presta attenzione a non creare articoli duplicati.
                    '))
                );

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/set-publishing-status', name: 'app_editor_article_set-publishing-status', methods: ['POST'])]
    public function setPublishingStatus(int $articleId, ArticlePlanner $articlePlanner) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);
            $previousPublishedAt = $this->articleEditor->getPublishedAt();

            $publishingStatus = $this->request->get('status');

            if( $publishingStatus == ArticleEditor::PUBLISHING_ACTION_PUBLISH_URGENTLY ) {

                $publishUrgently    = true;
                $publishingStatus   = ArticleEditor::PUBLISHING_STATUS_PUBLISHED;

            } else {

                $publishUrgently = false;
            }

            $mailer =
                $articlePlanner
                    ->setPublishingStatus($this->articleEditor, $publishingStatus, $publishUrgently)
                    ->getMailer();

            $this->articleEditor->save();

            $this->clearCachedArticle($previousPublishedAt);

            $jsonOkMessage = "Stato di pubblicazione modificato correttamente";

            return
                $this
                    ->handleNotification($mailer, $jsonOkMessage)
                    ->jsonOKResponse($jsonOkMessage);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
