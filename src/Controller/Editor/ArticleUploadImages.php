<?php
namespace App\Controller\Editor;

use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ArticleUploadImages extends ArticleEditBaseController
{
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/images/upload', name: 'app_article_edit_images-upload', methods: ['POST'])]
    public function upload(int $articleId) : JsonResponse|Response
    {
        try {

            $currentUserAsAuthor = $this->getCurrentUserAsAuthor();

            $this->loadArticleEditor($articleId);

            $uploadedImages = $this->request->files->get('images', []);

            $images =
                $this->factory->createImageEditorCollection()
                    ->setFromUpload($uploadedImages, $currentUserAsAuthor);

            $this->articleEditor->addImages($images);

            $this->factory->getEntityManager()->flush();

            return $this->render('article/editor/images.html.twig', [
                'Images'    => $images,
                'Article'   => $this->articleEditor
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
}
