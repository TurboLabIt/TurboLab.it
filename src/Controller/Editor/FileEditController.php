<?php
namespace App\Controller\Editor;

use App\Service\Cms\FileEditor;
use App\ServiceCollection\Cms\FileEditorCollection;
use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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


    #[Route('/ajax/editor/files/rename/{fileId<[1-9]+[0-9]*>}', name: 'app_file_edit-rename', methods: ['PATCH'])]
    public function rename(int $fileId) : JsonResponse|Response
    {
        try {
            $newTitle = $this->request->get('title');

            $this->loadFileEditor($fileId)
                ->setTitle($newTitle)
                ->save();

            return new Response('OK');

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }



    #[Route('/ajax/editor/files/delete/{fileId<[1-9]+[0-9]*>}', name: 'app_file_edit-delete', methods: ['DELETE'])]
    public function delete(int $fileId, FileEditor $file, #[MapQueryParameter] int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);
            $file->load($fileId);

            $this->articleEditor->removeFile($file);

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
