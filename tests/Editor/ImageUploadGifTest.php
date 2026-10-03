<?php
namespace App\Tests\Editor;

use App\Entity\Cms\Image as ImageEntity;
use App\Exception\InvalidEnumException;
use App\Service\Cms\Image;
use App\Service\Factory;
use App\Tests\BaseT;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * GIF is an upload-only format: authors upload it like a PNG or a JPEG, and it's served built in one
 * of the usual formats. GD reads only the first frame, so an animated GIF is served as a still image.
 *
 * @see \App\Entity\Cms\Image::getUploadFormats()
 * @see \App\Service\Cms\Image::build()
 */
class ImageUploadGifTest extends BaseT
{
    const int RED   = 0;
    const int BLUE  = 1;

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


    public function testGifIsAnUploadOnlyFormat() : void
    {
        $this->assertContains(ImageEntity::FORMAT_GIF, ImageEntity::getUploadFormats());
        $this->assertNotContains(ImageEntity::FORMAT_GIF, ImageEntity::getFormats());

        $this->expectException(InvalidEnumException::class);
        Image::setBuildFormat(ImageEntity::FORMAT_GIF);
    }


    public function testStaticGifIsServedLikeAPng() : void
    {
        $gd = imagecreate(200, 100);
        imagecolorallocate($gd, 0, 128, 0);

        $path = $this->tmpPath();
        imagegif($gd, $path);

        $this->uploadBuildAndCheck($path, 'grafico.gif', 'grafico', [200, 100], [0, 128, 0]);
    }


    public function testAnimatedGifIsServedAsItsFirstFrame() : void
    {
        $path = $this->makeAnimatedGif(200, 100, [[200, 100, 0, 0, self::RED], [200, 100, 0, 0, self::BLUE]]);
        $this->uploadBuildAndCheck($path, 'animazione.gif', 'animazione', [200, 100], [255, 0, 0]);
    }


    public function testFirstFrameSmallerThanTheCanvasIsNotStretched() : void
    {
        // a legal GIF: frame #1 is a 100x100 square inside a 400x200 canvas. getimagesize() reports the
        // canvas, GD decodes just the frame: sizing the build on the canvas would stretch the square 2:1
        $path = $this->makeAnimatedGif(400, 200, [[100, 100, 150, 50, self::RED], [400, 200, 0, 0, self::BLUE]]);
        $this->uploadBuildAndCheck($path, 'riquadro.gif', 'riquadro', [100, 100], [255, 0, 0]);
    }


    private function uploadBuildAndCheck(string $path, string $fileName, string $expectedTitle, array $expectedSize, array $expectedRgb) : void
    {
        // lossless, so the pixel check can be exact
        Image::setBuildFormat(ImageEntity::FORMAT_PNG);

        $file   = new UploadedFile($path, $fileName, 'image/gif', null, true /* test mode */);
        $image  = static::getService(Factory::class)->createImageEditor()->createFromUploadedFile($file);

        try {
            $this->assertSame(ImageEntity::FORMAT_GIF, $image->getFormat());
            $this->assertStringEndsWith('.gif', $image->getOriginalFilePath());
            $this->assertFileExists( $image->getOriginalFilePath() );
            $this->assertSame($expectedTitle, $image->getTitle());

            $built = imagecreatefromstring( $image->getContent(Image::SIZE_MED) );
            $this->assertSame('image/png', $image->getBuiltImageMimeType());
            $this->assertSame($expectedSize, [imagesx($built), imagesy($built)]);

            $centerColor = imagecolorsforindex($built, imagecolorat($built, intdiv($expectedSize[0], 2), intdiv($expectedSize[1], 2)));
            $this->assertSame($expectedRgb, [$centerColor['red'], $centerColor['green'], $centerColor['blue']]);

        } finally {
            $image->delete();
        }
    }


    /**
     * GD writes single-frame GIFs only: each frame is written by GD, then they are spliced into one GIF.
     * All the frames share the same 2-color palette, so they can share the global color table.
     *
     * @param array<array{0: int, 1: int, 2: int, 3: int, 4: int}> $frames [width, height, left, top, palette index]
     */
    private function makeAnimatedGif(int $canvasWidth, int $canvasHeight, array $frames) : string
    {
        $gif = null;
        foreach($frames as [$width, $height, $left, $top, $colorIndex]) {

            $gd = imagecreate($width, $height);
            $palette = [imagecolorallocate($gd, 255, 0, 0), imagecolorallocate($gd, 0, 0, 255)];
            imagefill($gd, 0, 0, $palette[$colorIndex]);

            ob_start();
            imagegif($gd);
            $bytes = ob_get_clean();

            // header (6 bytes) + logical screen descriptor (7) + global color table, then the image descriptor
            $packed         = ord($bytes[10]);
            $gctLength      = $packed & 0x80 ? 3 * (2 << ($packed & 0x07)) : 0;
            $descriptorAt   = 13 + $gctLength;
            $this->assertSame("\x2C", $bytes[$descriptorAt], 'Unexpected block in the GIF written by GD');

            $gif ??=
                'GIF89a' . pack('vv', $canvasWidth, $canvasHeight) . substr($bytes, 10, 3) .
                substr($bytes, 13, $gctLength) .
                "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";  // loop forever

            $gif .=
                "\x21\xF9\x04\x00\x0A\x00\x00\x00" .            // graphic control extension: 100 ms
                "\x2C" . pack('vv', $left, $top) .              // image descriptor, moved to left/top...
                substr($bytes, $descriptorAt + 5, -1);          // ...+ size, LZW data (no trailer)
        }

        $path = $this->tmpPath();
        file_put_contents($path, $gif . "\x3B");
        return $path;
    }


    private function tmpPath() : string
    {
        $path = tempnam(sys_get_temp_dir(), 'tli_gif_') . '.gif';
        $this->tmpFiles[] = $path;
        return $path;
    }
}
