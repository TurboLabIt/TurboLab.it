<?php
namespace App\Tests\Editor;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Image;
use App\Service\Cms\ImageEditor;
use App\Service\Cms\ImageFrames;
use App\Service\Factory;
use App\Tests\BaseT;
use App\Tests\ImageFixtures;
use Imagick;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * An animated GIF/WebP requested as WebP is built as an animated WebP, in every size, watermarked frame by frame.
 * In any other format, and for any other image (APNG and AVIF sequences included: Imagick can't read their
 * frames here), the build is a still. A single-frame "animated" WebP is a still (animated = 0), even though
 * GD can't open it.
 *
 * getUrl() points to the animated WebP of an animated GIF/WebP.
 *
 * Only one animated build runs at a time: a request that can't get its turn in time throws.
 *
 * @see \App\Service\Cms\Image::build()
 */
class ImageAnimatedBuildTest extends BaseT
{
    // a 600x400 canvas: [width, height, watermarked] of each size
    const array EXPECTED_SIZES = [
        Image::SIZE_MIN => [150, 100, false],
        Image::SIZE_MED => [375, 250, false],   // the watermark needs 300x300
        Image::SIZE_REG => [600, 400, true],
        Image::SIZE_MAX => [600, 400, true],
    ];

    const array RGB = [[255, 0, 0], [0, 0, 255], [0, 255, 0]];

    /** @var string[] */
    private array $tmpFiles = [];
    private ?string $previousBuildFormat = null;
    private int $previousWaitSeconds = 0;


    protected function setUp() : void
    {
        parent::setUp();
        // the build format is static: restore it, for the tests that run next in this process
        $this->previousBuildFormat = (new ReflectionProperty(Image::class, 'buildFileExtension'))->getValue();
        $this->previousWaitSeconds = (new ReflectionProperty(Image::class, 'animatedBuildWaitSeconds'))->getValue();
    }


    protected function tearDown() : void
    {
        (new ReflectionProperty(Image::class, 'buildFileExtension'))->setValue(null, $this->previousBuildFormat);
        (new ReflectionProperty(Image::class, 'animatedBuildWaitSeconds'))->setValue(null, $this->previousWaitSeconds);

        foreach($this->tmpFiles as $file) {
            @unlink($file);
        }
        $this->tmpFiles = [];
        parent::tearDown();
    }


    public function testAnimatedGif() : void
    {
        $gif = ImageFixtures::decodableGif(600, 400, [
            [600, 400, 0, 0, ImageFixtures::RED], [600, 400, 0, 0, ImageFixtures::BLUE], [600, 400, 0, 0, ImageFixtures::GREEN]
        ]);

        $this->checkAnimatedBuilds( $this->upload($gif, 'animazione.gif') );
    }


    public function testAnimatedWebp() : void
    {
        $webp = ImageFixtures::animatedWebpFromGd([$this->gdFill(0xFF0000), $this->gdFill(0x0000FF), $this->gdFill(0x00FF00)]);
        $this->checkAnimatedBuilds( $this->upload($webp, 'animazione.webp') );
    }


    public function testSingleFrameAnimatedWebpIsAStill() : void
    {
        $webp = ImageFixtures::animatedWebpFromGd([$this->gdFill(0xFF0000)]);
        $image = $this->upload($webp, 'animazione-singola.webp');

        try {
            $this->assertFalse($image->isAnimated());
            $this->assertFalse(@imagecreatefromwebp($image->getOriginalFilePath()), 'GD reads this file now: the Imagick detour may be useless');
            $this->checkStillBuilds($image, [ImageEntity::FORMAT_WEBP, ImageEntity::FORMAT_AVIF]);

        } finally {
            $image->delete();
        }
    }


    public function testApngIsFlaggedButBuiltAsAStill() : void
    {
        $image = $this->upload(ImageFixtures::apngFromGd([$this->gdFill(0xFF0000), $this->gdFill(0x0000FF)]), 'animazione.png');

        try {
            $this->assertTrue($image->isAnimated());
            $this->assertFalse($image->canBeBuiltAnimated());
            $this->assertStringEndsWith(
                '.' . Image::getClientSupportedBestFormat(), parse_url($image->getUrl(Image::SIZE_REG), PHP_URL_PATH)
            );
            $this->checkStillBuilds($image, [ImageEntity::FORMAT_WEBP, ImageEntity::FORMAT_AVIF]);

        } finally {
            $image->delete();
        }
    }


    public function testBusyAnimatedBuildGivesUp() : void
    {
        $gif = ImageFixtures::decodableGif(600, 400, [[600, 400, 0, 0, ImageFixtures::RED], [600, 400, 0, 0, ImageFixtures::BLUE]]);
        $image = $this->upload($gif, 'animazione.gif');

        // another animated build is running
        $globalLock = static::getService(Factory::class)->getLockFactory()->createLock(Image::LOCK_ANIMATED_BUILD);
        $this->assertTrue( $globalLock->acquire() );
        (new ReflectionProperty(Image::class, 'animatedBuildWaitSeconds'))->setValue(null, 1);

        try {
            Image::setBuildFormat(ImageEntity::FORMAT_WEBP);

            try {
                $image->build(Image::SIZE_REG);
                $this->fail('The animated build should give up while another one is running');

            } catch(RuntimeException $ex) {
                $this->assertStringContainsString('Another animated image is being built', $ex->getMessage());
                $this->assertFalse($image->tryPreBuilt(Image::SIZE_REG), 'Nothing must be cached by a build that gave up');
            }

            // the animation is built once the other build is done
            $globalLock->release();
            static::$client->request('GET', $image->getUrl(Image::SIZE_REG));
            $this->assertSame(200, static::$client->getResponse()->getStatusCode());
            $this->assertSame('image/webp', static::$client->getResponse()->headers->get('Content-Type'));

        } finally {
            $globalLock->release();
            $image->delete();
        }
    }


    private function checkAnimatedBuilds(ImageEditor $image) : void
    {
        try {
            $this->assertTrue($image->isAnimated());
            $this->assertTrue($image->canBeBuiltAnimated());
            foreach(array_keys(static::EXPECTED_SIZES) as $size) {
                $this->assertStringEndsWith('.webp', parse_url($image->getUrl($size), PHP_URL_PATH), "$size URL");
            }

            Image::setBuildFormat(ImageEntity::FORMAT_WEBP);
            foreach(static::EXPECTED_SIZES as $size => [$width, $height, $watermarked]) {

                $image->build($size);
                $this->assertSame('image/webp', $image->getBuiltImageMimeType());

                $built = $this->builtFilePath($image, $size);
                $this->assertSame(3, ImageFrames::read($built, ImageEntity::FORMAT_WEBP)->frames, "$size: every frame must be there");

                $frames = (new Imagick('webp:' . $built))->coalesceImages();
                foreach($frames as $index => $frame) {

                    $this->assertSame([$width, $height], [$frame->getImageWidth(), $frame->getImageHeight()], "$size, frame $index");
                    $this->assertColor(static::RGB[$index], $frame, intdiv($width, 2), intdiv($height, 2), "$size, frame $index");
                    $this->assertSame(
                        $watermarked, $this->hasWatermark($frame, static::RGB[$index]),
                        "$size, frame $index: watermark " . ($watermarked ? 'missing' : 'unexpected')
                    );
                }
            }

            // any other format is the still: the first frame
            $this->checkStillBuilds($image, [ImageEntity::FORMAT_AVIF, ImageEntity::FORMAT_PNG]);

        } finally {
            $image->delete();
        }
    }


    private function checkStillBuilds(ImageEditor $image, array $formats) : void
    {
        foreach($formats as $format) {

            Image::setBuildFormat($format);
            foreach(static::EXPECTED_SIZES as $size => [$width, $height]) {

                $image->build($size);
                $built = $this->builtFilePath($image, $size);

                $this->assertLessThanOrEqual(1, ImageFrames::read($built, $format)->frames, "$size $format must be a still");
                $this->assertSame([$width, $height], array_slice(getimagesize($built), 0, 2), "$size $format");
            }
        }
    }


    private function hasWatermark(Imagick $frame, array $fillRgb) : bool
    {
        // the watermark goes bottom-left: look for pixels far from the fill color there
        $height = $frame->getImageHeight();
        for($x = 5; $x < 170; $x += 3) {
            for($y = $height - 30; $y < $height - 2; $y += 3) {

                $pixel = $frame->getImagePixelColor($x, $y)->getColor();
                if( abs($pixel['r'] - $fillRgb[0]) + abs($pixel['g'] - $fillRgb[1]) + abs($pixel['b'] - $fillRgb[2]) > 60 ) {
                    return true;
                }
            }
        }

        return false;
    }


    private function assertColor(array $expectedRgb, Imagick $frame, int $x, int $y, string $message) : void
    {
        // lossy WebP may shift a flat color a little
        $pixel = $frame->getImagePixelColor($x, $y)->getColor();
        foreach(['r', 'g', 'b'] as $index => $channel) {
            $this->assertEqualsWithDelta($expectedRgb[$index], $pixel[$channel], 12, $message);
        }
    }


    private function upload(string $bytes, string $fileName) : ImageEditor
    {
        $path = tempnam(sys_get_temp_dir(), 'tli_animbuild_');
        $this->tmpFiles[] = $path;
        file_put_contents($path, $bytes);

        return
            static::getService(Factory::class)->createImageEditor()
                ->createFromUploadedFile( new UploadedFile($path, $fileName, null, null, true /* test mode */) );
    }


    private function gdFill(int $rgb) : \GdImage
    {
        $gd = imagecreatetruecolor(600, 400);
        imagefill($gd, 0, 0, $rgb);
        return $gd;
    }


    private function builtFilePath(Image $image, string $size) : string
    {
        return (new ReflectionMethod(Image::class, 'getBuiltFilePath'))->invoke($image, $size, false);
    }
}
