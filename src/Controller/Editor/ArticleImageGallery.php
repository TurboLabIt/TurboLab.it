<?php
namespace App\Controller\Editor;

use App\Service\Cms\ImageEditor;
use App\Service\Sentinel\ImageSentinel;
use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ArticleImageGallery extends ArticleEditBaseController
{
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/images/upload', name: 'app_article_edit_images-upload', methods: ['POST'])]
    public function upload(int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $uploadedImages = $this->request->files->get('images', []);

            $currentUserAsAuthor = $this->sentinel->getCurrentUserAsAuthor();

            $images =
                $this->factory->createImageEditorCollection()
                    ->setFromUpload($uploadedImages, $currentUserAsAuthor);

            $this->articleEditor->addImages($images, $currentUserAsAuthor);

            // there is no need to save() the article here
            $this->factory->getEntityManager()->flush();

            return $this->render('article/editor/images.html.twig', [
                'Images'    => $images,
                'Article'   => $this->articleEditor
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/images/delete/{imageId<[1-9]+[0-9]*>}', name: 'app_article_edit_images-delete', methods: ['DELETE'])]
    public function delete(int $articleId, int $imageId, ImageEditor $image, ImageSentinel $sentinel) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $image->load($imageId);
            $sentinel
                ->setImage($image)
                ->enforceCanDelete();

            $image->delete();

            return new Response('OK');

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
