<?php
namespace App\Controller;

use App\Exception\ImageLogicException;
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
    const string SECTION_SLUG = "immagini";


    public function __construct(protected Image $image, protected ImageCollection $imageCollection) {}


    #[Route('/' . self::SECTION_SLUG . '/{size<[a-z]+>}/{imageFolderMod}/{slugDashId<[^/]*-[1-9]+[0-9]*>}.{format<[^/]+>}', name: 'app_image')]
    public function index(string $size, string $imageFolderMod, string $slugDashId, string $format) : Response
    {
        $image = $this->getImageOr404Response($slugDashId, $size);

        if( $image instanceof Response ) {
            // 404
            return $image;
        }

        $imageRealUrl = $image->checkRealUrl($size, $imageFolderMod, $slugDashId, $format);
        if( !empty($imageRealUrl) ) {
            return $this->redirect($imageRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        $result = $image->tryPreBuilt($size);
        if($result) {
            return $this->xSendImage($image, $size, '1-tryPreBuilt');
        }

        $image->build($size);

        return $this->xSendImage($image, $size, '99-build');
    }


    protected function getImageOr404Response(string $slugDashId, string $size) : Image|Response
    {
        try {
            $image = $this->image->loadBySlugDashId($slugDashId);
            $image->checkSize($size);
            return $image;

        } catch(ImageNotFoundException|ImageLogicException) {

            $image404 = $this->imageCollection->get404();
            $size = in_array($size, $image404->getSizes() ) ? $size : Image::SIZE_MED;

            return
                new Response($image404->getContent($size), Response::HTTP_NOT_FOUND, [
                    'Content-Type' => $image404->getBuiltImageMimeType()
                ]);
        }
    }


    protected function xSendImage(Image $image, string $size, string $originHeader) : Response
    {
        $webserverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if( stripos( $webserverSoftware, 'nginx') === false ) {

            // the app is NOT running on Nginx => can't use X-Sendfile
            // we are likely in a test, running on Symfony integrated web server
            $response = new Response($image->getContent($size), Response::HTTP_OK, [
                'Content-Type' => $image->getBuiltImageMimeType()
            ]);

            $response->headers->set('x-tli-image', $originHeader . '-no-xsendfile');

            return $response;
        }

        // running on Nginx => use XSendfile

        $response =
            // 📕 the HTTP Status code here is IGNORED by the X-Sendfile location
            new Response('', Response::HTTP_OK, [
                'Content-Type' => $image->getBuiltImageMimeType()
            ]);

        // this doesn't work (it doesn't survive the internal redirect of X-Sendfile)
        $response->headers->set('x-tli-image', $originHeader);

        // https://stackoverflow.com/a/44323940/1204976
        $xSendPath = $image->getXSendPath($size);
        $response->headers->set('X-Accel-Redirect', $xSendPath);

        return $response;
    }


    #[Route('/' . self::SECTION_SLUG . '/{id<[1-9]+[0-9]*>}/{size<[a-z]+>}', name: 'app_image_shorturl')]
    public function shortUrl(string $id, string $size = Image::SIZE_MAX) : Response
    {
        return $this->redirectToRealImage($size, "image-$id");
    }


    #[Route('/' . self::SECTION_SLUG . '/{size<[a-z]+>}/{slugDashId<[^/]*-[1-9]+[0-9]*>}.{format<[^/]+>}', name: 'app_image_legacy_no-folder-mod')]
    public function legacyNoFolderMod(string $size, string $slugDashId) : Response
    {
        return $this->redirectToRealImage($size, $slugDashId);
    }


    protected function redirectToRealImage(string $size, string $slugDashId) : Response
    {
        $image = $this->getImageOr404Response($slugDashId, $size);

        if( $image instanceof Response ) {
            // 404
            return $image;
        }

        $imageRealUrl =  $image->getUrl($size);
        return $this->redirect($imageRealUrl, Response::HTTP_MOVED_PERMANENTLY);
    }
}
