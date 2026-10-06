<?php
namespace App\Tests\Editor;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\ImageFrames;
use App\Tests\BaseT;
use App\Tests\ImageFixtures;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * On upload, ImageFrames reads how many frames an image has, the canvas they are drawn on and whether one
 * overflows it: ImageEditor flags the animated ones (Image.animated) and enforces the animation limits.
 * Only the container structure is read, never the pixels: the fixtures are hand-made, structurally valid
 * files, not necessarily decodable.
 *
 * @see \App\Service\Cms\ImageFrames
 */
class ImageAnimatedDetectionTest extends BaseT
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


    public static function craftedFileProvider() : array
    {
        $gifComment = "\x21\xFE\x05\x2C\x2C\x2C\x2C\x2C\x00";   // a comment full of image-descriptor bytes
        $gifOneFrameWithComments =
            'GIF89a' . pack('vvCCC', 2, 2, 0x80, 0, 0) . "\x00\x00\x00\xFF\xFF\xFF" .
            $gifComment . "\x2C" . pack('vvvvC', 0, 0, 2, 2, 0) . "\x02\x02\x44\x01\x00" . $gifComment . "\x3B";

        $png = "\x89PNG\r\n\x1A\n" . ImageFixtures::pngChunk('IHDR', pack('NNCCCCC', 2, 2, 8, 6, 0, 0, 0));

        // format, file, expected: [frames, canvas width, canvas height, a frame outside the canvas]
        return [
            'GIF, 3 frames'                     => ['gif',  ImageFixtures::gif(40, 30, [[0, 0, 40, 30], [5, 5, 10, 10], [0, 0, 40, 30]]), [3, 40, 30, false]],
            'GIF, 1 frame'                      => ['gif',  ImageFixtures::gif(40, 30, [[0, 0, 40, 30]]), [1, 40, 30, false]],
            'GIF, 0x2C in comments'             => ['gif',  $gifOneFrameWithComments, [1, 2, 2, false]],
            'GIF, truncated after two frames'   => ['gif',  substr(ImageFixtures::gif(4, 4, [[0, 0, 4, 4], [0, 0, 4, 4]]), 0, -1), [2, 4, 4, false]],
            'GIF, frame larger than canvas'     => ['gif',  ImageFixtures::gif(10, 10, [[0, 0, 20000, 20000]]), [1, 10, 10, true]],
            'GIF, frame moved outside canvas'   => ['gif',  ImageFixtures::gif(10, 10, [[0, 0, 10, 10], [5, 0, 10, 10]]), [2, 10, 10, true]],
            'GIF, garbage'                      => ['gif',  'GIF89a', [0, 0, 0, false]],

            'WebP, 2 frames'                    => ['webp', ImageFixtures::webp(64, 32, [[0, 0, 64, 32], [10, 10, 20, 20]]), [2, 64, 32, false]],
            'WebP, frame outside canvas'        => ['webp', ImageFixtures::webp(64, 32, [[0, 0, 64, 32], [50, 0, 20, 20]]), [2, 64, 32, true]],
            'WebP, VP8X without animation flag' => ['webp', ImageFixtures::webp(64, 32, [], false), [1, 0, 0, false]],
            'WebP, truncated'                   => ['webp', 'RIFF', [0, 0, 0, false]],

            'APNG, 3 frames'                    => ['png',  ImageFixtures::apng(30, 20, 3, [[0, 0, 30, 20], [0, 0, 30, 20], [5, 5, 5, 5]]), [3, 30, 20, false]],
            'APNG, declaring more than it has'  => ['png',  ImageFixtures::apng(30, 20, 5000, [[0, 0, 30, 20], [0, 0, 30, 20]]), [5000, 30, 20, false]],
            'APNG, frame outside canvas'        => ['png',  ImageFixtures::apng(30, 20, 2, [[0, 0, 30, 20], [20, 0, 30, 20]]), [2, 30, 20, true]],
            'APNG, 1 frame'                     => ['png',  ImageFixtures::apng(30, 20, 1, [[0, 0, 30, 20]]), [1, 30, 20, false]],
            'PNG, acTL after IDAT (not APNG)'   => ['png',  $png . ImageFixtures::pngChunk('IDAT', 'x') . ImageFixtures::pngChunk('acTL', pack('NN', 3, 0)), [1, 2, 2, false]],
            'PNG, chunk shorter than its fields'=> ['png',  "\x89PNG\r\n\x1A\n" . pack('N', 0) . 'IHDR' . str_repeat("\x00", 12), [0, 0, 0, false]],
            'PNG, truncated'                    => ['png',  "\x89PNG", [0, 0, 0, false]],

            'AVIF sequence'                     => ['avif', ImageFixtures::avifSequence(128, 72, 24), [24, 128, 72, false]],
            'AVIF sequence, compatible brand'   => ['avif', ImageFixtures::avifSequence(128, 72, 24, ['avif', 'mif1', 'avis']), [24, 128, 72, false]],
            'AVIF still'                        => ['avif', ImageFixtures::ftyp('avif', 'mif1', 'miaf'), [1, 0, 0, false]],
            'AVIF, avis as minor version only'  => ['avif', pack('N', 16) . 'ftypavifavis', [1, 0, 0, false]],
            'AVIF sequence without moov'        => ['avif', ImageFixtures::ftyp('avis', 'avif'), [1, 0, 0, false]],
            'AVIF, truncated'                   => ['avif', "\x00\x00", [0, 0, 0, false]],

            'JPEG is never animated'            => ['jpg',  ImageFixtures::gif(4, 4, [[0, 0, 4, 4], [0, 0, 4, 4]]), [1, 0, 0, false]],
        ];
    }


    #[DataProvider('craftedFileProvider')]
    public function testCraftedFile(string $format, string $bytes, array $expected) : void
    {
        $frames = ImageFrames::read($this->writeTmp($bytes), $format);
        $this->assertSame($expected, [$frames->frames, $frames->canvasWidth, $frames->canvasHeight, $frames->frameOutsideCanvas]);
        $this->assertSame($expected[0] > 1, $frames->isAnimated());
    }


    public function testCountingStopsAfterTheGivenFrame() : void
    {
        $path = $this->writeTmp( ImageFixtures::gif(2, 2, array_fill(0, 50, [0, 0, 2, 2])) );
        $this->assertSame(11, ImageFrames::read($path, 'gif', 11)->frames);
        $this->assertSame(50, ImageFrames::read($path, 'gif')->frames);
    }


    public function testStillImagesWrittenByGd() : void
    {
        $gd = imagecreatetruecolor(16, 16);

        foreach([
            ImageEntity::FORMAT_GIF     => 'imagegif',
            ImageEntity::FORMAT_PNG     => 'imagepng',
            ImageEntity::FORMAT_WEBP    => 'imagewebp',
            ImageEntity::FORMAT_AVIF    => 'imageavif',
            ImageEntity::FORMAT_JPG     => 'imagejpeg',
        ] as $format => $fxWrite) {

            ob_start();
            $fxWrite($gd);
            $frames = ImageFrames::read( $this->writeTmp(ob_get_clean()), $format );

            $this->assertSame(1, $frames->frames, "A still $format image must have 1 frame");
            $this->assertFalse($frames->frameOutsideCanvas);
        }
    }


    private function writeTmp(string $bytes) : string
    {
        $path = tempnam(sys_get_temp_dir(), 'tli_anim_');
        $this->tmpFiles[] = $path;
        file_put_contents($path, $bytes);
        return $path;
    }
}
