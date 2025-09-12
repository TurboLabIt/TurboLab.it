<?php
namespace App\Controller;

use App\Service\Cms\Visit;
use App\Service\Cms\File;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class FileController extends BaseController
{
    const string SECTION_SLUG = "scarica";


    public function __construct(protected File $file, RequestStack $requestStack)
    {
        $this->request = $requestStack->getCurrentRequest();
    }


    #[Route('/' . self::SECTION_SLUG . '/{fileId<[1-9]+[0-9]*>}', name: 'app_file')]
    public function index(int $fileId, Visit $visit) : Response
    {
        $file = $this->file->load($fileId);

        $visit->visit($file, $this->getCurrentUser());

        if( $file->isLocal() ) {
            return $this->xSendFile($file);
        }

        $url = $file->getExternalDownloadUrl();
        return $this->redirect($url, Response::HTTP_FOUND);
    }


    protected function xSendFile(File $file) : Response
    {
        $webserverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if( stripos( $webserverSoftware, 'nginx') === false ) {

            // the app is NOT running on Nginx => can't use X-Sendfile
            // we are likely in a test, running on Symfony integrated web server
            $response = new Response($file->getContent(), Response::HTTP_OK, [
                'Content-Type' => $file->getMimeType()
            ]);

            $response->headers->set('Content-Disposition', 'attachment; filename="' . $file->getUserPerceivedFileName() . '"');
            $response->headers->set('x-tli-file', 'no-xsendfile');

            return $response;
        }

        // running on Nginx => use XSendfile

        $response =
            // 📕 the HTTP Status code here is IGNORED by the X-Sendfile location
            new Response('', Response::HTTP_OK, [
                'Content-Type' => $file->getMimeType()
            ]);

        $response->headers->set('Content-Disposition', 'attachment; filename="' . $file->getUserPerceivedFileName() . '"');

        // https://stackoverflow.com/a/44323940/1204976
        $xSendPath = $file->getXSendPath();
        $response->headers->set('X-Accel-Redirect', $xSendPath);

        return $response;
    }
}
