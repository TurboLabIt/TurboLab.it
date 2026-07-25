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


    private function upload(int $width, int $height) : void
    {
        $path = $this->makePng($width, $height);
        $file = new UploadedFile($path, basename($path), 'image/png', null, true /* test mode */);

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
