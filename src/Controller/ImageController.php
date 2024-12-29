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
    const string SECTION_SLUG = "immagini";


    public function __construct(protected Image $image, protected ImageCollection $imageCollection) {}


    #[Route('/' . self::SECTION_SLUG . '/{size<micro|slider|min|med|max>}/{imageFolderMod}/{slugDashId<[^/]+-[1-9]+[0-9]*>}.{format<[^/]+>}', name: 'app_image')]
    public function index($size, $slugDashId) : Response
    {
        return $this->build($size, $slugDashId);
    }


    #[Route('/' . self::SECTION_SLUG . '/{id<[1-9]+[0-9]*>}/{size<micro|slider|min|med|max>}', name: 'app_image_shorturl')]
    public function shortUrl($id, $size = Image::SIZE_MAX) : Response
    {
        return $this->build($size, "image-$id");
    }


    protected function build($size, $slugDashId) : Response
    {
        // try direct filesystem access, without db
        $entityId = substr($slugDashId, strrpos($slugDashId, '-') + 1);
        $entity =
            (new ImageEntity())
                ->setId($entityId)
                ->setFormat( Image::getClientSupportedBestFormat() );

        $imageNoDb  = $this->image->setEntity($entity);
        $result     = $imageNoDb->tryPreBuilt($size);
        if($result) {
            return $this->xSendImage($imageNoDb, $size, '1-tryPreBuilt');
        }

        //
        try {
            $image = $this->image->loadBySlugDashId($slugDashId);

            // let's tryPreBuilt again (the previous direct try via $imageNoDb may have failed due to... reasons?)
            $result = $image->tryPreBuilt($size);
            if($result) {
                return $this->xSendImage($image, $size, '2-tryPreBuilt-again');
            }

            $image->build($size);

        } catch(ImageNotFoundException $ex) {

            $image404 = $this->imageCollection->get404();
            // can't use X-Sendfile: it always returns 200, but the status code here MUST be 404
            return
                new Response($image404->getContent($size), Response::HTTP_NOT_FOUND, [
                    'Content-Type' => $image404->getBuiltImageMimeType()
                ]);
        }

        return $this->xSendImage($image, $size, '99-build');
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


    #[Route('/' . self::SECTION_SLUG . '/{size<micro|slider|min|med|max>}/{slugDashId<[^/]+-[1-9]+[0-9]*>}.{format<[^/]+>}', name: 'app_image_legacy_no-folder-mod')]
    public function legacyNoFolderMod($size, $slugDashId) : Response
    {
        return $this->build($size, $slugDashId);
    }
}
