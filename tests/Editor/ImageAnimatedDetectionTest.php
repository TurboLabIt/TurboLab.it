<?php
namespace App\Tests\Editor;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\ImageEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;


/**
 * On upload, ImageEditor flags the images holding more than one frame (Image.animated). Detection reads
 * the container structure only, never the pixels: these fixtures are mostly hand-made headers, enough
 * for the parser but not necessarily decodable.
 *
 * @see \App\Service\Cms\ImageEditor::isAnimatedFile()
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
        $gifScreen  = 'GIF89a' . pack('vvCCC', 2, 2, 0x80, 0, 0) . "\x00\x00\x00\xFF\xFF\xFF";
        $gifFrame   = "\x2C" . pack('vvvvC', 0, 0, 2, 2, 0) . "\x02\x02\x44\x01\x00";
        $gifGce     = "\x21\xF9\x04\x00\x0A\x00\x00\x00";
        $gifLoop    = "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";
        $gifComment = "\x21\xFE\x05\x2C\x2C\x2C\x2C\x2C\x00";   // a comment full of image-descriptor bytes

        $webp       = fn(int $flags) => 'RIFF' . pack('V', 22) . 'WEBPVP8X' . pack('V', 10) . chr($flags) . str_repeat("\x00", 9);

        $pngChunk   = fn(string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $pngIhdr    = "\x89PNG\r\n\x1A\n" . $pngChunk('IHDR', pack('NNCCCCC', 2, 2, 8, 6, 0, 0, 0));
        $acTL       = fn(int $frames) => $pngChunk('acTL', pack('NN', $frames, 0));

        $ftyp       = fn(string ...$brands) =>
            pack('N', 12 + 4 * count($brands)) . 'ftyp' . $brands[0] . "\x00\x00\x00\x00" . implode('', array_slice($brands, 1));

        return [
            'GIF, two frames'                   => ['gif',  $gifScreen . $gifLoop . $gifGce . $gifFrame . $gifGce . $gifFrame . "\x3B", true],
            'GIF, one frame'                    => ['gif',  $gifScreen . $gifGce . $gifFrame . "\x3B", false],
            'GIF, one frame + 0x2C in comment'  => ['gif',  $gifScreen . $gifComment . $gifFrame . $gifComment . "\x3B", false],
            'GIF, truncated after two frames'   => ['gif',  $gifScreen . $gifFrame . $gifFrame, true],
            'GIF, garbage'                      => ['gif',  'GIF89a', false],

            'WebP, animation flag'              => ['webp', $webp(0x02), true],
            'WebP, animation + alpha flags'     => ['webp', $webp(0x12), true],
            'WebP, alpha flag only'             => ['webp', $webp(0x10), false],
            'WebP, truncated'                   => ['webp', 'RIFF', false],

            'APNG, 3 frames'                    => ['png',  $pngIhdr . $acTL(3) . $pngChunk('IDAT', 'x') . $pngChunk('IEND', ''), true],
            'APNG, 1 frame'                     => ['png',  $pngIhdr . $acTL(1) . $pngChunk('IDAT', 'x') . $pngChunk('IEND', ''), false],
            'PNG, acTL after IDAT (not APNG)'   => ['png',  $pngIhdr . $pngChunk('IDAT', 'x') . $acTL(3) . $pngChunk('IEND', ''), false],
            'PNG, truncated'                    => ['png',  "\x89PNG", false],

            'AVIF sequence, major brand'        => ['avif', $ftyp('avis', 'avif', 'msf1', 'miaf'), true],
            'AVIF sequence, compatible brand'   => ['avif', $ftyp('avif', 'mif1', 'avis'), true],
            'AVIF still'                        => ['avif', $ftyp('avif', 'mif1', 'miaf'), false],
            'AVIF, avis as minor version only'  => ['avif', pack('N', 16) . 'ftypavifavis', false],
            'AVIF, truncated'                   => ['avif', "\x00\x00", false],

            'JPEG is never animated'            => ['jpg',  $gifScreen . $gifFrame . $gifFrame, false],
        ];
    }


    #[DataProvider('craftedFileProvider')]
    public function testCraftedFile(string $format, string $bytes, bool $expected) : void
    {
        $this->assertSame($expected, $this->isAnimatedFile($this->writeTmp($bytes), $format));
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
            $path = $this->writeTmp( ob_get_clean() );

            $this->assertFalse($this->isAnimatedFile($path, $format), "A still $format image was flagged as animated");
        }
    }


    private function isAnimatedFile(string $path, string $format) : bool
    {
        $editor = static::getService(Factory::class)->createImageEditor();
        return (new ReflectionMethod(ImageEditor::class, 'isAnimatedFile'))->invoke($editor, $path, $format);
    }


    private function writeTmp(string $bytes) : string
    {
        $path = tempnam(sys_get_temp_dir(), 'tli_anim_');
        $this->tmpFiles[] = $path;
        file_put_contents($path, $bytes);
        return $path;
    }
}
