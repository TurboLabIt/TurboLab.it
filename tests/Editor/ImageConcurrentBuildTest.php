<?php
namespace App\Tests\Editor;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Image;
use App\Service\Cms\ImageEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use Imagick;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * A still is written aside and renamed into place: a request that comes while another one is building the same image
 * never finds it empty or half-written. tryPreBuilt() only checks that the file exists, and GD creates it before
 * encoding into it, so on an empty image cache (scripts/cache-clear.sh empties it, the deploy too) two test workers or
 * two visitors asking for the same image at once could get it with 0 bytes and a 200 (NewsTest, 2026-11-18).
 *
 * A forked child builds while this process looks at the cache, as a concurrent request would. The child ends by
 * SIGKILL: it shares the database connection with this process, and its destructors would close it.
 *
 * @see \App\Service\Cms\Image::build()
 */
class ImageConcurrentBuildTest extends BaseT
{
    private ?string $previousBuildFormat = null;
    private ?string $tmpFile = null;


    protected function setUp() : void
    {
        parent::setUp();
        // the build format is static: restore it, for the tests that run next in this process
        $this->previousBuildFormat = (new ReflectionProperty(Image::class, 'buildFileExtension'))->getValue();
    }


    protected function tearDown() : void
    {
        (new ReflectionProperty(Image::class, 'buildFileExtension'))->setValue(null, $this->previousBuildFormat);

        if( $this->tmpFile !== null && file_exists($this->tmpFile) ) {
            unlink($this->tmpFile);
        }

        parent::tearDown();
    }


    public function testARequestDuringTheBuildNeverFindsAPartialFile() : void
    {
        $image = $this->uploadFixture();

        try {
            // AVIF is the slowest to encode, so the widest window: about 60 ms for this fixture, on an idle box
            Image::setBuildFormat(ImageEntity::FORMAT_AVIF);
            $builtFilePath = (new ReflectionMethod(Image::class, 'getBuiltFilePath'))->invoke($image, Image::SIZE_MAX, false);
            $this->assertFileDoesNotExist($builtFilePath);

            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'pcntl_fork() failed');

            if($pid === 0) {

                try {
                    $image->build(Image::SIZE_MAX);
                } finally {
                    // no destructor, no shutdown function
                    posix_kill(posix_getpid(), SIGKILL);
                }
            }

            // the size a concurrent request would find, again and again until the child is done
            $arrSeenSizes = [];
            do {
                $childDone = pcntl_waitpid($pid, $status, WNOHANG) !== 0;

                clearstatcache(true, $builtFilePath);
                if( file_exists($builtFilePath) ) {
                    $arrSeenSizes[] = filesize($builtFilePath);
                }

                usleep(200);

            } while( !$childDone );

            clearstatcache(true, $builtFilePath);
            $this->assertFileExists($builtFilePath, 'The build in the child process did not complete');
            $finalSize = filesize($builtFilePath);
            $this->assertGreaterThan(0, $finalSize);

            $arrPartial = array_filter($arrSeenSizes, fn(int|false $size) => $size !== $finalSize);
            $this->assertCount(
                0, $arrPartial,
                "During the build the image was found " . count($arrPartial) . " times with " .
                implode(', ', array_unique($arrPartial)) . " bytes instead of $finalSize: " .
                "a concurrent request would have served that"
            );

            $this->assertSame([], glob("$builtFilePath.*.part"), 'The build left its temporary file behind');

        } finally {
            $image->delete();
        }
    }


    private function uploadFixture() : ImageEditor
    {
        // plasma is random: a new image at every run
        $imagick = new Imagick();
        $imagick->newPseudoImage(1600, 1000, 'plasma:fractal');
        $imagick->setImageFormat(ImageEntity::FORMAT_PNG);

        $this->tmpFile = tempnam(sys_get_temp_dir(), 'tli_concurrent_build_');
        file_put_contents($this->tmpFile, $imagick->getImageBlob());

        return
            static::getService(Factory::class)->createImageEditor()
                ->createFromUploadedFile( new UploadedFile($this->tmpFile, 'build-concorrente.png', null, null, true /* test mode */) );
    }
}
