<?php
namespace App\Controller;

use App\Entity\Cms\Image as ImageEntity;
use App\Exception\ImageNotFoundException;
use App\Service\Cms\Image;
use App\ServiceCollection\Cms\ImageCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md
 */
class ImageController extends BaseController
{
    public function __construct(protected ImageCollection $imageCollection)
    { }


    #[Route('/immagini/{size<min|med|max>}/{imageFolderMod}/{imageSlugDashId<[^/]+-[1-9]+[0-9]*>}.{format<[^/]+>}', name: 'app_image')]
    public function index($size, $imageFolderMod, $imageSlugDashId, $format) : Response
    {
        // try direct filesystem access, without db
        $entityId = substr($imageSlugDashId, strrpos($imageSlugDashId, '-') + 1);
        $entity =
            (new ImageEntity())
                ->setId($entityId)
                ->setFormat($format);

        $imageNoDb  = $this->imageCollection->createService($entity);
        $result     = $imageNoDb->tryPreBuilt($size);

        if($result) {
            return $this->xSendImage($imageNoDb, $size);
        }

        //
        try {
            $image = $this->imageCollection->loadBySlugDashId($imageSlugDashId);

        } catch(ImageNotFoundException $ex) {

            $image404 = $this->imageCollection->get404($size);
            // can't use X-Sendfile: the status code MUST be 404, not 200
            return
                new Response($image404->getContent($size), Response::HTTP_NOT_FOUND, [
                    'Content-Type' => $image404->getBuiltImageMimeType()
                ]);
        }

        // let's tryPreBuilt again (the previous direct try via $imageNoDb may have failed due to the requested format being wrong)
        $result = $image->tryPreBuilt($size);

        if($result) {
            return $this->xSendImage($image, $size);
        }

        $image->build($size);
        return $this->xSendImage($image, $size);
    }


    protected function xSendImage(Image $image, string $size) : Response
    {
        $response =
            // 📕 the HTTP Status code here is IGNORED by the X-Sendfile location
            new Response('', Response::HTTP_OK, [
                'Content-Type' => $image->getBuiltImageMimeType()
            ]);

        // https://stackoverflow.com/a/44323940/1204976
        $xSendPath = $image->getXSendPath($size);
        $response->headers->set('X-Accel-Redirect', $xSendPath);

        return $response;
    }


    #[Route('/immagini/{size<min|med|max>}/{imageSlugDashId<[^/]+-[1-9]+[0-9]*>}.{format<[^/]+>}', name: 'app_image_legacy_no_folder_mod')]
    public function legacyNoFolderMod($size, $imageSlugDashId, $format) : Response
    {
        return $this->index($size, 0, $imageSlugDashId, $format);
    }


    #[Route('/immagini/{imageId<[1-9]+[0-9]*>}/{size<min|med|max>}', name: 'app_image_legacy_id_size_only')]
    public function legacyIdAndSize($imageId, $size) : Response
    {
        return $this->index($size, 0, "image-$imageId", Image::EXTENSION_BEST_FORMAT);
    }
}
