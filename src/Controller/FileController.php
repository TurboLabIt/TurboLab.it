<?php
namespace App\Controller;

use App\Service\Cms\Visit;
use App\Service\Cms\File;
use App\Service\FrontendHelper;
use App\ServiceCollection\Cms\FileCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TurboLabIt\BaseCommand\Service\ProjectDir;


class FileController extends BaseController
{
    const string SECTION_SLUG = "scarica";


    #[Route('/' . self::SECTION_SLUG . '/{fileId<[1-9]+[0-9]*>}', name: 'app_file')]
    public function index(int $fileId, Visit $visit) : Response
    {
        $file = $this->factory->createFile()->load($fileId);

        $user = $this->getCurrentUserAsAuthor();
        $visit->visit($file, $user);

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


    #[Route('/' . self::SECTION_SLUG . '/da-controllare', name: 'app_file_need-fixing')]
    public function needFixing(ProjectDir $projectDir, FrontendHelper $frontendHelper, FileCollection $files) : Response
    {
        $filePathUnused     = $projectDir->getVarDirFromFilePath(File::ATTACHED_BUT_UNUSED_FILE_NAME);
        $txtJson            = file_get_contents($filePathUnused);
        $arrAttachedUnused  = json_decode($txtJson, true);

        $orphans = $files->loadOrphans();

        $filePathMissingOnFilesystem = $projectDir->getVarDirFromFilePath(File::MISSING_ON_FILESYSTEM_FILE_NAME);
        $txtJson                    = file_get_contents($filePathMissingOnFilesystem);
        $arrMissingOnFilesystem     = json_decode($txtJson, true);

        return $this->render('file/need-fixing.html.twig', [
            'metaTitle'                 => 'File da controllare',
            'activeMenu'                => null,
            'FrontendHelper'            => $frontendHelper,
            //
            'AttachedUnused'            => $arrAttachedUnused,
            'numAttachedUnused'         => number_format(count($arrAttachedUnused), 0, ',', '.'),
            'dateAttachedUnusedList'    => new \DateTime('@' . filemtime($filePathUnused)),
            //
            'Orphans'                   => $files,
            'numOrphans'                => number_format($orphans->count(), 0, ',', '.'),
            //
            'MissingOnFilesystem'       => $arrMissingOnFilesystem,
            'numMissingOnFilesystem'    => number_format(count($arrMissingOnFilesystem), 0, ',', '.')
        ]);
    }
}
