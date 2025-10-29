<?php
namespace App\Controller\Editor;

use App\Service\Cms\FileEditor;
use App\Service\Sentinel\FileSentinel;
use App\ServiceCollection\Cms\FileEditorCollection;
use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ArticleEditFilesController extends ArticleEditBaseController
{
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/files/upload', name: 'app_article_edit_files-upload', methods: ['POST'])]
    public function upload(int $articleId) : JsonResponse|Response
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


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/files/delete/{fileId<[1-9]+[0-9]*>}',
        name: 'app_article_edit_files-delete', methods: ['DELETE'], condition: "'%kernel.environment%' != 'prod'")]
    public function delete(int $articleId, int $fileId, FileEditor $file, FileSentinel $sentinel) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $file->load($fileId);
            $sentinel
                ->setFile($file)
                ->enforceCanDelete();

            $file->delete();

            return new Response('OK');

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
