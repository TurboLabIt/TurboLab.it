<?php
namespace App\Tests\Security;

use App\Service\Cms\Image;
use App\Service\Cms\ImageEditor;
use App\Service\Cms\ImageFrames;
use App\Service\Factory;
use App\Tests\BaseT;
use App\Tests\ImageFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;


/**
 * Sibling of ImageUploadPixelCapTest (docs/security-audit.md #32). Animated images are built by decoding
 * every frame at once, outside PHP's memory_limit (about 13 MB of RAM per megapixel, measured), so on
 * upload ImageEditor rejects with 422:
 *
 *  - any image with a frame overflowing its canvas (malformed, or a bomb hiding a huge frame behind a
 *    small declared size: getimagesize() only sees the canvas);
 *  - animated images wider or taller than Image::RESOLUTION_MAX_ANIMATED;
 *  - animated images with more than Image::ANIMATED_FRAMES_MAX frames;
 *  - animated images over Image::ANIMATED_PIXEL_BUDGET (width x height x frames: ~7 GB of RAM).
 *
 * The limits are read from the file structure, never by decoding it (see ImageFrames).
 */
class ImageUploadAnimationLimitsTest extends BaseT
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


    public function testLimits() : void
    {
        $this->assertSame(4096, Image::RESOLUTION_MAX_ANIMATED);
        $this->assertSame(1860, Image::ANIMATED_FRAMES_MAX);
        $this->assertSame(500_000_000, Image::ANIMATED_PIXEL_BUDGET);
    }


    public static function rejectedGifProvider() : array
    {
        return [
            'frame larger than the canvas (still)'  => [10, 10, [[0, 0, 20000, 20000]], 'malformata'],
            'frame moved outside the canvas'        => [10, 10, [[0, 0, 10, 10], [5, 0, 10, 10]], 'malformata'],
            'animated, too wide'                    => [Image::RESOLUTION_MAX_ANIMATED + 1, 4, array_fill(0, 2, [0, 0, 1, 1]), 'troppo grande'],
            'animated, too tall'                    => [4, Image::RESOLUTION_MAX_ANIMATED + 1, array_fill(0, 2, [0, 0, 1, 1]), 'troppo grande'],
            'too many frames'                       => [1, 1, array_fill(0, Image::ANIMATED_FRAMES_MAX + 1, [0, 0, 1, 1]), 'troppi fotogrammi'],
            // 4096 x 4096 x 30 = 503 MP
            'over the pixel budget'                 => [4096, 4096, array_fill(0, 30, [0, 0, 1, 1]), 'troppo pesante'],
        ];
    }


    #[DataProvider('rejectedGifProvider')]
    public function testUploadRejects(int $canvasWidth, int $canvasHeight, array $frames, string $reason) : void
    {
        $path = $this->writeTmp( ImageFixtures::gif($canvasWidth, $canvasHeight, $frames) );
        $file = new UploadedFile($path, 'animazione.gif', 'image/gif', null, true /* test mode */);

        // real upload entry point; the check throws before anything is persisted or moved
        $this->expectException(UnprocessableEntityHttpException::class);
        // ...and for the expected reason, not by an earlier check
        $this->expectExceptionMessage($reason);
        static::getService(Factory::class)->createImageEditor()->createFromUploadedFile($file);
    }


    public static function otherFormatsProvider() : array
    {
        return [
            'APNG declaring too many frames'        => ['png',  ImageFixtures::apng(30, 20, Image::ANIMATED_FRAMES_MAX + 1, [[0, 0, 30, 20]])],
            'APNG frame outside the canvas'         => ['png',  ImageFixtures::apng(30, 20, 2, [[0, 0, 30, 20], [20, 0, 30, 20]])],
            'animated WebP, too wide'               => ['webp', ImageFixtures::webp(Image::RESOLUTION_MAX_ANIMATED + 2, 10, array_fill(0, 2, [0, 0, 10, 10]))],
            'AVIF sequence, too many frames'        => ['avif', ImageFixtures::avifSequence(16, 16, Image::ANIMATED_FRAMES_MAX + 1)],
            'AVIF sequence, over the pixel budget'  => ['avif', ImageFixtures::avifSequence(4096, 4096, 30)],
        ];
    }


    #[DataProvider('otherFormatsProvider')]
    public function testOtherFormatsAreRejected(string $format, string $bytes) : void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->assertWithinAnimationLimits($bytes, $format);
    }


    public static function acceptedProvider() : array
    {
        return [
            // 4096 x 4096 x 29 = 486 MP
            'animated, at the resolution limit, within budget'  => ['gif',  ImageFixtures::gif(4096, 4096, array_fill(0, 29, [0, 0, 1, 1]))],
            'animated, exactly at the frame limit'              => ['gif',  ImageFixtures::gif(1, 1, array_fill(0, Image::ANIMATED_FRAMES_MAX, [0, 0, 1, 1]))],
            'still, over the animated resolution limit'         => ['gif',  ImageFixtures::gif(Image::RESOLUTION_MAX, 1, [[0, 0, Image::RESOLUTION_MAX, 1]])],
            'AVIF sequence at the frame limit'                  => ['avif', ImageFixtures::avifSequence(16, 16, Image::ANIMATED_FRAMES_MAX)],
            'APNG'                                              => ['png',  ImageFixtures::apng(640, 360, 300, [[0, 0, 640, 360], [0, 0, 640, 360]])],
        ];
    }


    #[DataProvider('acceptedProvider')]
    public function testAccepted(string $format, string $bytes) : void
    {
        $this->assertInstanceOf(ImageEditor::class, $this->assertWithinAnimationLimits($bytes, $format));
    }


    private function assertWithinAnimationLimits(string $bytes, string $format) : ImageEditor
    {
        $frames = ImageFrames::read($this->writeTmp($bytes), $format, Image::ANIMATED_FRAMES_MAX + 1);
        $editor = static::getService(Factory::class)->createImageEditor();
        return (new ReflectionMethod(ImageEditor::class, 'assertWithinAnimationLimits'))->invoke($editor, $frames);
    }


    private function writeTmp(string $bytes) : string
    {
        $path = tempnam(sys_get_temp_dir(), 'tli_animlimits_');
        $this->tmpFiles[] = $path;
        file_put_contents($path, $bytes);
        return $path;
    }
}
