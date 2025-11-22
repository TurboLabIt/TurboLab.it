<?php
namespace App\Controller\Editor;

use App\Service\Cms\FileEditor;
use App\ServiceCollection\Cms\FileEditorCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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
                'Article' => $this->articleEditor
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/files/get-modal/{fileId<[1-9]+[0-9]*>}/{articleId<[1-9]+[0-9]*>}', name: 'app_file_edit-get_modal', methods: ['GET'])]
    public function getEditModal(int $fileId, int $articleId) : Response
    {
        try {
            $this->loadFileEditor($fileId);

            return $this->json([
                "title" => "📝 Modifica file: " . $this->fileEditor->getTitle(),
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
                ->setTitle( $this->request->get('title') )
                ->setFormat( $this->request->get('format') )
                ->setExternalDownloadUrl( $this->fileEditor->isExternal() ? $this->request->get('remote-url') : null )
                ->save();

            return $this->render('article/files.html.twig', [
                'Article' => $this->factory->createArticle()->load($articleId)
            ]);

        } catch(UniqueConstraintViolationException $ex) {

            if ($ex->getCode() != 1062 || stripos($ex, 'Duplicate entry') === false) {
                throw $ex;
            }

            $title              = $this->fileEditor->getTitle();
            $originalFileUrl    = $this->factory->createFile()->loadByTitle($title)->getUrl();

            return
                $this->textErrorResponse(
                    new ConflictHttpException(
                        "Impossibile salvare: esiste già un file con questo titolo ( $originalFileUrl ). " .
                        "Per favore, presta attenzione a non creare file duplicati."
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

            $this->articleEditor->removeFile($fileEditor);

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
}
