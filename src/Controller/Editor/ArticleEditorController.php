<?php
namespace App\Controller\Editor;

use App\Exception\TopicHasRepliesException;
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

                $value = $this->request->request->get($param);
                $method = "set" . ucfirst($param);
                $this->articleEditor->$method($value);
            }

            $this->articleEditor->save();

            $jsonOkMessage = "Articolo salvato";

            return
                $this
                    ->clearCachedArticle()
                    ->createCommentsTopicPlaceholder($jsonOkMessage)
                    ->jsonOKResponse($jsonOkMessage);

        } catch(UniqueConstraintViolationException $ex) {

            if ($ex->getCode() != 1062 || stripos($ex, 'Duplicate entry') === false) {
                throw $ex;
            }

            $title              = $this->articleEditor->getTitle();
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


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/change-format/{format<[0-9]>}', name: 'app_editor_article_change-format', methods: ['POST'])]
    public function changeFormat(int $articleId, int $format) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            if( $this->articleEditor->getFormat() == $format ) {
                return $this->jsonOKResponse('Il formato editoriale è già quello desiderato. Nessuna modifica necessaria');
            }

            $this->articleEditor
                ->setFormat($format)
                ->save();

            return
                $this
                    ->clearCachedArticle()
                    ->jsonOKResponse("Formato editoriale modificato correttamente.");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/set-publishing-status', name: 'app_editor_article_set-publishing-status', methods: ['POST'])]
    public function setPublishingStatus(int $articleId, ArticlePlanner $articlePlanner) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $previousPublishedAt    = $this->articleEditor->getPublishedAt();
            $publishingStatus       = $this->request->request->get('status');

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

            $jsonOkMessage = "Stato di pubblicazione modificato correttamente";

            return
                $this
                    ->clearCachedArticle($previousPublishedAt)
                    ->handleNotification($mailer, $jsonOkMessage)
                    ->createCommentsTopicPlaceholder($jsonOkMessage)
                    ->jsonOKResponse($jsonOkMessage);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    protected function createCommentsTopicPlaceholder(string $jsonOkMessage) : static
    {
        try {
            $this->articleEditor
                ->createCommentsTopicPlaceholder()
                ->save();

        } catch(Exception $ex) {
            throw new Exception($jsonOkMessage .
                ". Si è però verificato un errore durante la creazione del topic dei commenti: " . $ex->getMessage()
            );
        }

        return $this;
    }


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/delete', name: 'app_editor_article_delete', methods: ['POST'])]
    public function delete(int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);
            $this->sentinel->enforceCanDelete();

            $articleDeletedMessage = "Articolo eliminato correttamente - ";

            $commentsTopic = $this->articleEditor->getCommentsTopic();

            if( empty($commentsTopic) ) {

                $commentsTopicHtmlLink = '';
                $okMessage = "$articleDeletedMessage L'articolo non aveva ancora un topic di commento associato, " .
                    "quindi non è stato necessario eliminare null'altro";

            } else {

                $commentsTopicUrl = $commentsTopic->getUrl();
                $commentsTopicHtmlLink = "<a href=\"$commentsTopicUrl\" target=\"_blank\">relativo topic dei commenti</a>";

                $okMessage = "$articleDeletedMessage Il $commentsTopicHtmlLink non conteneva risposte, ed è stato anch'esso eliminato";
            }


            try {

                $response =
                    $this
                        ->clearCachedArticle()
                        ->jsonOKResponse($okMessage);

                $this->articleEditor->delete();

                return $response;

            } catch(TopicHasRepliesException) {

                $commentsNum = $commentsTopic?->getPostNum() ?? 0;
                // the very first message of the topic is still a post, so a topic without comments still have num=1
                $commentsNum--;

                $commentsNumText = $commentsNum == 1 ? "1 risposta" : "$commentsNum risposte";

                throw new TopicHasRepliesException(
                    "$articleDeletedMessage Tuttavia, il $commentsTopicHtmlLink contiene $commentsNumText, e quindi non è stato eliminato. " .
                    "Devi valutare la qualità/rilevanza di tali risposte e, in caso, contattare un moderatore per richiedere la cancellazione manuale del topic"
                );
            }

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
