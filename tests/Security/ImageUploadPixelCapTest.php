<?php
namespace App\Tests\Security;

use App\Service\Cms\Image;
use App\Service\Cms\ImageEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;


/**
 * Anti-regression guard for docs/security-audit.md finding #32
 * ("Decompression bomb: decodifica GD senza cap sui pixel, da richiesta anonima") — now RESOLVED.
 *
 * Image uploads used to be validated only for MIME prefix (image/*) and non-zero size; nothing
 * capped width×height. A tiny file declaring huge dimensions (e.g. 30000×30000) would be stored,
 * then Image::build() — triggered by an anonymous GET on an uncached variant — opened it with GD
 * and allocated hundreds of MB per request (a build that dies mid-way caches nothing, so the work
 * repeats on every request).
 *
 * Fix: ImageEditor::createFromUploadedFile() now rejects any original whose width or height exceeds
 * Image::RESOLUTION_MAX (12000px) at the upload trust boundary, reading dimensions with getimagesize()
 * (header only — it does NOT decode the pixels, so the check itself is bomb-safe). build() is
 * deliberately left unchanged: the cap is enforced once, on upload.
 *
 * NB: 12001×1 etc. are a few KB in memory — an over-cap *dimension*, not a real memory bomb — so
 * these fixtures exercise the guard cheaply.
 *
 * GIF (upload-only, first frame only): getimagesize() reads the logical screen, not the frames. The cap
 * still bounds build() because GD decodes only the first frame and refuses a frame that overflows the
 * screen — see testGdRefusesGifFrameLargerThanTheLogicalScreen().
 */
class ImageUploadPixelCapTest extends BaseT
{
    /** @var string[] */
    private array $tmpFiles = [];


    protected function tearDown() : void
    {
        foreach($this->tmpFiles as $file) {
            @unlink($file);
        }
        $this->tmpFiles = [];
        parent::tearDown();
    }


    private function makePng(int $width, int $height) : string
    {
        $path = tempnam(sys_get_temp_dir(), 'tli_imgcap_') . '.png';
        $this->tmpFiles[] = $path;

        $image = imagecreatetruecolor($width, $height);
        imagepng($image, $path);

        return $path;
    }


    private function makeGif(int $width, int $height) : string
    {
        $path = tempnam(sys_get_temp_dir(), 'tli_imgcap_') . '.gif';
        $this->tmpFiles[] = $path;

        $image = imagecreate($width, $height);
        imagecolorallocate($image, 0, 0, 0);
        imagegif($image, $path);

        return $path;
    }


    private function upload(int $width, int $height, bool $asGif = false) : void
    {
        $path = $asGif ? $this->makeGif($width, $height) : $this->makePng($width, $height);
        $file = new UploadedFile($path, basename($path), $asGif ? 'image/gif' : 'image/png', null, true /* test mode */);

        // real upload entry point; the guard throws before anything is persisted or moved
        static::getService(Factory::class)->createImageEditor()->createFromUploadedFile($file);
    }


    public function testCapConstantIs12000Pixels() : void
    {
        $this->assertSame(12000, Image::RESOLUTION_MAX);
    }


    public function testUploadRejectsImageWiderThanCap() : void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->upload(Image::RESOLUTION_MAX + 1, 1);
    }


    public function testUploadRejectsImageTallerThanCap() : void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->upload(1, Image::RESOLUTION_MAX + 1);
    }


    public function testUploadRejectsGifWiderThanCap() : void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->upload(Image::RESOLUTION_MAX + 1, 1, true);
    }


    public function testGdRefusesGifFrameLargerThanTheLogicalScreen() : void
    {
        // 61 bytes: a 10x10 logical screen carrying a single 20000x20000 frame
        $gif =
            'GIF89a' . pack('vvCCC', 10, 10, 0, 0, 0) .                                         // logical screen, no global color table
            "\x2C" . pack('vvvvC', 0, 0, 20000, 20000, 0x80) . "\x00\x00\x00\xFF\xFF\xFF" .     // frame + 2-color local color table
            "\x02\x01\x2C\x00" .                                                                // LZW data: clear code + end of information
            "\x3B";

        $path = tempnam(sys_get_temp_dir(), 'tli_imgcap_') . '.gif';
        $this->tmpFiles[] = $path;
        file_put_contents($path, $gif);

        // the upload guard only sees the 10x10 screen...
        [$width, $height] = getimagesize($path);
        $this->assertSame([10, 10], [$width, $height]);

        $assertWithinPixelCap = new ReflectionMethod(ImageEditor::class, 'assertWithinPixelCap');
        $editor = static::getService(Factory::class)->createImageEditor();
        $this->assertSame($editor, $assertWithinPixelCap->invoke($editor, $path));

        // ...so the frame must be refused by GD (imagecreatefromstring() is what Imagine runs in build()),
        // not allocated. An animation-aware decoder such as Imagick does allocate it: it needs its own guard
        $this->assertFalse( @imagecreatefromstring($gif) );
    }


    public function testGuardAcceptsImagesUpToTheCap() : void
    {
        $assertWithinPixelCap = new ReflectionMethod(ImageEditor::class, 'assertWithinPixelCap');
        $editor = static::getService(Factory::class)->createImageEditor();

        // within-cap and exactly-at-cap must pass (assertWithinPixelCap returns $this on success)
        foreach ([[100, 100], [Image::RESOLUTION_MAX, 1], [1, Image::RESOLUTION_MAX]] as [$width, $height]) {
            $this->assertSame(
                $editor,
                $assertWithinPixelCap->invoke($editor, $this->makePng($width, $height)),
                "A {$width}x{$height}px image (within cap) must be accepted."
            );
        }
    }
}
