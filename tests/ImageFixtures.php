<?php
namespace App\Tests;


/**
 * Hand-made image files for the tests on ImageFrames and the upload limits. They carry a valid container
 * structure (what ImageFrames reads) but no real pixel data: none of them is meant to be decoded.
 *
 * Every frame is [left, top, width, height].
 */
class ImageFixtures
{
    // palette indexes of decodableGif()
    const int RED   = 0;
    const int BLUE  = 1;
    const int GREEN = 2;


    /**
     * A real, decodable GIF. GD writes single-frame GIFs only: each frame is written by GD, then they are
     * spliced into one GIF. All the frames share the same palette, so they can share the global color table.
     *
     * @param array<array{0: int, 1: int, 2: int, 3: int, 4: int}> $frames [width, height, left, top, palette index]
     */
    public static function decodableGif(int $canvasWidth, int $canvasHeight, array $frames) : string
    {
        $gif = null;
        foreach($frames as [$width, $height, $left, $top, $colorIndex]) {

            $gd = imagecreate($width, $height);
            $palette = [
                imagecolorallocate($gd, 255, 0, 0), imagecolorallocate($gd, 0, 0, 255), imagecolorallocate($gd, 0, 255, 0)
            ];
            imagefill($gd, 0, 0, $palette[$colorIndex]);

            ob_start();
            imagegif($gd);
            $bytes = ob_get_clean();

            // header (6 bytes) + logical screen descriptor (7) + global color table, then the image descriptor
            $packed         = ord($bytes[10]);
            $gctLength      = $packed & 0x80 ? 3 * (2 << ($packed & 0x07)) : 0;
            $descriptorAt   = 13 + $gctLength;
            if( $bytes[$descriptorAt] !== "\x2C" ) {
                throw new \RuntimeException('Unexpected block in the GIF written by GD');
            }

            $gif ??=
                'GIF89a' . pack('vv', $canvasWidth, $canvasHeight) . substr($bytes, 10, 3) .
                substr($bytes, 13, $gctLength) .
                "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";  // loop forever

            $gif .=
                "\x21\xF9\x04\x00\x0A\x00\x00\x00" .            // graphic control extension: 100 ms
                "\x2C" . pack('vv', $left, $top) .              // image descriptor, moved to left/top...
                substr($bytes, $descriptorAt + 5, -1);          // ...+ size, LZW data (no trailer)
        }

        return $gif . "\x3B";
    }


    public static function gif(int $canvasWidth, int $canvasHeight, array $frames) : string
    {
        // 2-color global color table
        $gif =
            'GIF89a' . pack('vvCCC', $canvasWidth, $canvasHeight, 0x80, 0, 0) . "\x00\x00\x00\xFF\xFF\xFF" .
            "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";      // loop forever

        foreach($frames as [$left, $top, $width, $height]) {
            $gif .=
                "\x21\xF9\x04\x00\x0A\x00\x00\x00" .            // graphic control extension: 100 ms
                "\x2C" . pack('vvvvC', $left, $top, $width, $height, 0) .
                "\x02\x02\x44\x01\x00";                         // LZW data
        }

        return $gif . "\x3B";
    }


    public static function webp(int $canvasWidth, int $canvasHeight, array $frames, bool $animationFlag = true) : string
    {
        $uint24 = fn(int $value) => substr(pack('V', $value), 0, 3);

        $chunks =
            'VP8X' . pack('V', 10) . chr($animationFlag ? 0x02 : 0) . "\x00\x00\x00" .
            $uint24($canvasWidth - 1) . $uint24($canvasHeight - 1) .
            'ANIM' . pack('V', 6) . "\x00\x00\x00\x00\x00\x00";

        foreach($frames as [$left, $top, $width, $height]) {

            // frame header (16 bytes), then a fake 2-byte frame payload
            $payload =
                $uint24(intdiv($left, 2)) . $uint24(intdiv($top, 2)) . $uint24($width - 1) . $uint24($height - 1) .
                $uint24(100) . "\x00" . "\x00\x00";

            $chunks .= 'ANMF' . pack('V', strlen($payload)) . $payload;
        }

        return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
    }


    /**
     * A real, decodable animated WebP: every GD image becomes one full-canvas frame (100 ms)
     *
     * @param \GdImage[] $gdFrames all of the same size
     */
    public static function animatedWebpFromGd(array $gdFrames) : string
    {
        $uint24 = fn(int $value) => substr(pack('V', $value), 0, 3);
        $width  = imagesx($gdFrames[0]);
        $height = imagesy($gdFrames[0]);

        $chunks =
            'VP8X' . pack('V', 10) . chr(0x02) . "\x00\x00\x00" . $uint24($width - 1) . $uint24($height - 1) .
            'ANIM' . pack('V', 6) . "\x00\x00\x00\x00\x00\x00";

        foreach($gdFrames as $gd) {

            ob_start();
            imagewebp($gd, null, 100);
            // the frame bitstream: the chunks after the RIFF header ("RIFF" + size + "WEBP")
            $bitstream = substr(ob_get_clean(), 12);

            $payload = "\x00\x00\x00\x00\x00\x00" . $uint24($width - 1) . $uint24($height - 1) . $uint24(100) . "\x00" . $bitstream;
            $chunks .= 'ANMF' . pack('V', strlen($payload)) . $payload . (strlen($payload) % 2 ? "\x00" : '');
        }

        return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
    }


    public static function apng(int $canvasWidth, int $canvasHeight, int $declaredFrames, array $frames) : string
    {
        $png = "\x89PNG\r\n\x1A\n" . static::pngChunk('IHDR', pack('NNCCCCC', $canvasWidth, $canvasHeight, 8, 6, 0, 0, 0));
        $png .= static::pngChunk('acTL', pack('NN', $declaredFrames, 0));

        foreach($frames as $index => [$left, $top, $width, $height]) {
            $png .= static::pngChunk('fcTL', pack('NNNNNnnCC', $index, $width, $height, $left, $top, 10, 100, 0, 0));
            $png .= static::pngChunk($index ? 'fdAT' : 'IDAT', 'x');
        }

        return $png . static::pngChunk('IEND', '');
    }


    /**
     * A real, decodable APNG: every GD image (all of the same size and type) becomes one full-canvas frame
     *
     * @param \GdImage[] $gdFrames
     */
    public static function apngFromGd(array $gdFrames) : string
    {
        $png = null;
        $sequence = 0;
        foreach($gdFrames as $index => $gd) {

            ob_start();
            imagepng($gd);
            $bytes = ob_get_clean();

            // the chunks of this PNG: [type => concatenated data]
            $chunks = [];
            for($offset = 8; $offset < strlen($bytes); $offset += 12 + $length) {
                $length = unpack('N', $bytes, $offset)[1];
                $type   = substr($bytes, $offset + 4, 4);
                $chunks[$type] = ($chunks[$type] ?? '') . substr($bytes, $offset + 8, $length);
            }

            $png ??=
                "\x89PNG\r\n\x1A\n" . static::pngChunk('IHDR', $chunks['IHDR']) .
                static::pngChunk('acTL', pack('NN', count($gdFrames), 0));

            $png .= static::pngChunk('fcTL', pack('NNNNNnnCC', $sequence++, imagesx($gd), imagesy($gd), 0, 0, 10, 100, 0, 0));
            $png .= $index ? static::pngChunk('fdAT', pack('N', $sequence++) . $chunks['IDAT']) : static::pngChunk('IDAT', $chunks['IDAT']);
        }

        return $png . static::pngChunk('IEND', '');
    }


    public static function pngChunk(string $type, string $data) : string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }


    public static function avifSequence(int $width, int $height, int $samples, array $brands = ['avis', 'avif', 'msf1', 'miaf']) : string
    {
        $box = fn(string $type, string $payload) => pack('N', 8 + strlen($payload)) . $type . $payload;

        // visual sample entry: reserved (6), data reference index (2), pre-defined + reserved (16), width, height
        $av01 = $box('av01', str_repeat("\x00", 6) . pack('n', 1) . str_repeat("\x00", 16) . pack('nn', $width, $height));
        $stsd = $box('stsd', pack('NN', 0, 1) . $av01);
        $stsz = $box('stsz', pack('NNN', 0, 0, $samples) . str_repeat(pack('N', 100), min($samples, 10)));

        $moov = $box('moov', $box('trak', $box('mdia', $box('minf', $box('stbl', $stsd . $stsz)))));

        return static::ftyp(...$brands) . $moov . $box('mdat', 'x');
    }


    public static function ftyp(string ...$brands) : string
    {
        return pack('N', 12 + 4 * count($brands)) . 'ftyp' . $brands[0] . "\x00\x00\x00\x00" . implode('', array_slice($brands, 1));
    }
}
