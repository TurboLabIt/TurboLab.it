<?php
namespace App\Controller\Editor;

use App\Service\Cms\Article;
use App\Service\Cms\FileEditor;
use App\ServiceCollection\Cms\FileEditorCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;


class FileEditController extends ArticleEditBaseController
{
    protected FileEditor $fileEditor;


    #[Route('/ajax/editor/files/upload', name: 'app_file_edit-upload', methods: ['POST'])]
    public function upload(#[MapQueryParameter] int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $uploadedFiles = $this->request->files->get('files', []);

            $currentUserAsAuthor = $this->sentinel->getCurrentUserAsAuthor();

            $files =
                (new FileEditorCollection($this->factory))
                    ->setFromUpload($uploadedFiles, $currentUserAsAuthor);

            $this->articleEditor->addFiles($files, $currentUserAsAuthor);

            // there is no need to save() the article here
            $this->factory->getEntityManager()->flush();

            return $this->render('article/files.html.twig', [
                'Article'           => $this->articleEditor,
                'BitTorrentGuide'   => $this->factory->createArticle()->load(Article::ID_BITTORRENT_GUIDE),
                'EmuleGuide'        => $this->factory->createArticle()->load(Article::ID_EMULE_GUIDE)
            ]);

        } catch(UniqueConstraintViolationException $ex) {

            return $this->buildDuplicateTitleResponse($ex);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/files/upload-from-modal', name: 'app_file_edit-upload_from_modal', methods: ['POST'])]
    public function uploadFromModal(#[MapQueryParameter] int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $uploadedFiles = $this->request->files->get('files', []);
            $title         = trim( (string)$this->request->request->get('title', '') );

            if( empty($title) ) {
                throw new BadRequestHttpException("Il titolo è obbligatorio.");
            }

            $currentUserAsAuthor = $this->sentinel->getCurrentUserAsAuthor();

            $files =
                (new FileEditorCollection($this->factory))
                    ->setFromUpload($uploadedFiles, $currentUserAsAuthor, $title);

            $this->articleEditor->addFiles($files, $currentUserAsAuthor);
            $this->factory->getEntityManager()->flush();

            $fileEditor = null;
            foreach($files as $f) { $fileEditor = $f; break; }

            if( empty($fileEditor) ) {
                throw new BadRequestHttpException("Nessun file ricevuto.");
            }

            return $this->buildCreatedFileJsonResponse($fileEditor);

        } catch(UniqueConstraintViolationException $ex) {

            return $this->buildDuplicateTitleResponse($ex);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/files/create-from-url', name: 'app_file_edit-create_from_url', methods: ['POST'])]
    public function createFromUrl(#[MapQueryParameter] int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $url    = trim( (string)$this->request->request->get('url', '') );
            $title  = trim( (string)$this->request->request->get('title', '') );
            $format = trim( (string)$this->request->request->get('format', '') );

            if( empty($url) || empty($title) || empty($format) ) {
                throw new BadRequestHttpException("URL, titolo e formato sono obbligatori.");
            }

            if( filter_var($url, FILTER_VALIDATE_URL) === false ) {
                throw new BadRequestHttpException("L'URL inserito non è valido.");
            }

            $currentUserAsAuthor = $this->sentinel->getCurrentUserAsAuthor();

            $fileEditor =
                $this->factory->createFileEditor()
                    ->createFromUrl($url, $title, $format);

            $fileEditor->addAuthor($currentUserAsAuthor);

            $this->articleEditor->addFiles([$fileEditor], $currentUserAsAuthor);
            $this->factory->getEntityManager()->flush();

            return $this->buildCreatedFileJsonResponse($fileEditor);

        } catch(UniqueConstraintViolationException $ex) {

            return $this->buildDuplicateTitleResponse($ex);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/files/get-modal/{fileId<[1-9]+[0-9]*>}/{articleId<[1-9]+[0-9]*>}', name: 'app_file_edit-get_modal', methods: ['GET'])]
    public function getEditModal(int $fileId, int $articleId) : Response
    {
        try {
            $this->loadFileEditor($fileId);

            return $this->json([
                "title" => "📝 Modifica file: " . $this->fileEditor->getTitle() . " | #" . $this->fileEditor->getId(),
                "body"  => $this->twig->render('file/editor/modal.html.twig', [
                    'File'      => $this->fileEditor,
                    'Article'   => $this->factory->createArticle()->load($articleId),
                    'Formats'   => $this->factory->createFileCollection()->getFormats()
                ])
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/file/update/{fileId<[1-9]+[0-9]*>}/{articleId<[1-9]+[0-9]*>}', name: 'app_editor_file_update', methods: ['POST'])]
    public function update(int $fileId, int $articleId) : JsonResponse|Response
    {
        try {

            $this->loadFileEditor($fileId)
                ->setTitle( $this->request->request->get('title') )
                ->setFormat( $this->request->request->get('format') )
                ->setExternalDownloadUrl( $this->fileEditor->isExternal() ? $this->request->request->get('remote-url') : null )
                ->setAutoHash()
                ->save();

            return $this->render('article/files.html.twig', [
                'Article'           => $this->factory->createArticle()->load($articleId),
                'BitTorrentGuide'   => $this->factory->createArticle()->load(Article::ID_BITTORRENT_GUIDE),
                'EmuleGuide'        => $this->factory->createArticle()->load(Article::ID_EMULE_GUIDE)
            ]);

        } catch(UniqueConstraintViolationException $ex) {

            if( $ex->getCode() != 1062 || stripos($ex, 'Duplicate entry') === false ) {
                throw $ex;
            }

            $title = $this->fileEditor->getTitle();
            $originalFileUrl = $this->factory->createFile()->loadByTitle($title)->getUrl();

            return
                $this->textErrorResponse(
                    new ConflictHttpException(
                        "Impossibile salvare: esiste già un file dal titolo: $title ( $originalFileUrl ). " . PHP_EOL . PHP_EOL .
                        "Per favore, presta la massima attenzione a non creare file duplicati. " . PHP_EOL . PHP_EOL .
                        "🆕 Se stai cercando di creare la nuova versione di un file pre-esistente, devi aggiornare il vecchio file. " . PHP_EOL . PHP_EOL .
                        "🎏 Se invece si tratta dello stesso file in formato diverso, esplicitalo nel titolo. Ad es.: per Linux, (portable), ..."
                    )
                );

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/files/detach-from-article/{fileId<[1-9]+[0-9]*>}/{articleId<[1-9]+[0-9]*>}', name: 'app_file_edit-detach_from_article', methods: ['DELETE'])]
    public function detachFromArticle(int $fileId, int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);
            $fileEditor = $this->factory->createFileEditor()->load($fileId);

            $arrUsedBy = $fileEditor->getArticles();

            // this file is linked by 0 or 1 article ➡ delete the file directly
            if( count($arrUsedBy) < 2 ) {

                $fileEditor->delete();

            // this file is linked by 2 or more articles ➡ just detach from the current article
            } else {

                $this->articleEditor->removeFile($fileEditor);
            }


            // there is no need to save() the article here
            $this->factory->getEntityManager()->flush();

            return new Response('OK');

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    protected function loadFileEditor(int $fileId) : FileEditor
    {
        $this->ajaxOnly();

        $this->loginRequired();

        return
            $this->fileEditor =
                $this->factory->createFileEditor()->load($fileId)
                    ->enforceCanEdit();
    }


    protected function buildCreatedFileJsonResponse(FileEditor $fileEditor) : JsonResponse
    {
        return $this->json([
            'id'                    => $fileEditor->getId(),
            'downloadUrl'           => $fileEditor->getUrl(),
            'title'                 => $fileEditor->getTitle(),
            'userPerceivedFileName' => $fileEditor->getUserPerceivedFileName(),
            'attachedFilesHtml'     => $this->twig->render('article/files.html.twig', [
                'Article'           => $this->articleEditor,
                'BitTorrentGuide'   => $this->factory->createArticle()->load(Article::ID_BITTORRENT_GUIDE),
                'EmuleGuide'        => $this->factory->createArticle()->load(Article::ID_EMULE_GUIDE)
            ])
        ]);
    }


    protected function buildDuplicateTitleResponse(UniqueConstraintViolationException $ex) : Response
    {
        if( $ex->getCode() != 1062 || stripos($ex, 'Duplicate entry') === false ) {
            throw $ex;
        }

        return
            $this->textErrorResponse(
                new ConflictHttpException(
                    "Impossibile salvare: esiste già un file con questo titolo. " . PHP_EOL . PHP_EOL .
                    "Per favore, presta la massima attenzione a non creare file duplicati. " . PHP_EOL . PHP_EOL .
                    "🆕 Se stai cercando di creare la nuova versione di un file pre-esistente, devi aggiornare il vecchio file. " . PHP_EOL . PHP_EOL .
                    "🎏 Se invece si tratta dello stesso file in formato diverso, esplicitalo nel titolo. Ad es.: per Linux, (portable), ..."
                )
            );
    }
}
