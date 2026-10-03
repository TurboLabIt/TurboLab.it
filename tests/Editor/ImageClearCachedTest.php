<?php
namespace App\Tests\Editor;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Image;
use App\Service\Cms\ImageEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * An image is built on demand in whatever format is requested (AVIF on the web, PNG for og:image and the
 * newsletter, ...), and nginx serves any built file directly. ImageEditor::clearCached() used to delete
 * only the files of the format of the current request (AVIF, for the editor AJAX calls and the CLI), so:
 *
 *  - after a watermark change, the other formats kept the old watermark until the next deploy;
 *  - a deleted image stayed reachable in the other formats until the next deploy.
 *
 * @see \App\Service\Cms\ImageEditor::clearCached()
 */
class ImageClearCachedTest extends BaseT
{
    /** @var string[] */
    private array $tmpFiles = [];
    private ?string $previousBuildFormat = null;


    protected function setUp() : void
    {
        parent::setUp();
        // the build format is static: restore it, for the tests that run next in this process
        $this->previousBuildFormat = (new ReflectionProperty(Image::class, 'buildFileExtension'))->getValue();
    }


    protected function tearDown() : void
    {
        (new ReflectionProperty(Image::class, 'buildFileExtension'))->setValue(null, $this->previousBuildFormat);

        foreach($this->tmpFiles as $file) {
            @unlink($file);
        }
        $this->tmpFiles = [];
        parent::tearDown();
    }


    public function testWatermarkChangeClearsEveryFormat() : void
    {
        $image = $this->uploadAndBuildEveryFormat();

        try {
            // the editor AJAX request runs in its own format
            Image::setBuildFormat(ImageEntity::FORMAT_AVIF);
            $image->setWatermarkPosition(ImageEntity::WATERMARK_TOP_LEFT);

            foreach(ImageEntity::getFormats() as $format) {

                Image::setBuildFormat($format);
                foreach(Image::SIZES as $size) {
                    $this->assertFalse($image->tryPreBuilt($size), "The $size $format build survived the watermark change");
                }
            }

        } finally {
            $image->delete();
        }
    }


    public function testDeleteLeavesNoBuildBehind() : void
    {
        $image = $this->uploadAndBuildEveryFormat();

        $getBuiltFilePath = new ReflectionMethod(Image::class, 'getBuiltFilePath');
        $arrBuiltFiles = [];
        foreach(ImageEntity::getFormats() as $format) {
            foreach([Image::SIZE_MED, Image::SIZE_MAX] as $size) {

                $filePath = $getBuiltFilePath->invoke($image, $size, false, $format);
                $this->assertFileExists($filePath);
                $arrBuiltFiles[] = $filePath;
            }
        }

        $originalFilePath = $image->getOriginalFilePath();

        // the delete request (editor AJAX or ImagesDelete cron) runs in its own format
        Image::setBuildFormat(ImageEntity::FORMAT_AVIF);
        $image->delete();

        $this->assertFileDoesNotExist($originalFilePath);
        foreach($arrBuiltFiles as $filePath) {
            $this->assertFileDoesNotExist($filePath, "A build of the deleted image survived, and nginx would keep serving it");
        }
    }


    private function uploadAndBuildEveryFormat() : ImageEditor
    {
        $path = tempnam(sys_get_temp_dir(), 'tli_imgcache_') . '.png';
        $this->tmpFiles[] = $path;
        imagepng(imagecreatetruecolor(400, 400), $path);

        $file   = new UploadedFile($path, 'cache-test.png', 'image/png', null, true /* test mode */);
        $image  = static::getService(Factory::class)->createImageEditor()->createFromUploadedFile($file);

        foreach(ImageEntity::getFormats() as $format) {

            Image::setBuildFormat($format);
            foreach([Image::SIZE_MED, Image::SIZE_MAX] as $size) {
                $image->build($size);
                $this->assertTrue($image->tryPreBuilt($size), "The $size $format build is missing");
            }
        }

        return $image;
    }
}
