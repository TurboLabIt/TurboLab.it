<?php
namespace App\Service\Cms;

use App\Entity\Cms\Image as ImageEntity;


/**
 * The frames of an image file: how many, the canvas they are drawn on, and whether any of them overflows it.
 * Only the container structure is read, never the pixels: this runs on untrusted uploads, before anything
 * decodes them, so it must stay cheap whatever the file declares.
 *
 * A still image has 1 frame; 0 means the structure couldn't be read (the file is then handled as a still).
 *
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-articles.md
 */
final class ImageFrames
{
    public int $frames          = 0;
    public int $canvasWidth     = 0;
    public int $canvasHeight    = 0;
    public bool $frameOutsideCanvas = false;

    // the moov box of an AVIF sequence is read in memory: no legit one comes close (4 bytes per frame)
    const int AVIF_MOOV_MAX_BYTES = 16 * 1024 * 1024;


    /**
     * @param int $stopAfter frames beyond this are not counted (nor checked): the caller rejects the file anyway
     */
    public static function read(string $filePath, string $format, int $stopAfter = PHP_INT_MAX) : self
    {
        $frames = new self();

        $handle = fopen($filePath, 'rb');
        if( $handle === false ) {
            return $frames;
        }

        try {
            match($format) {
                ImageEntity::FORMAT_GIF     => $frames->readGif($handle, $stopAfter),
                ImageEntity::FORMAT_WEBP    => $frames->readWebp($handle, $stopAfter),
                ImageEntity::FORMAT_PNG     => $frames->readPng($handle, $stopAfter),
                ImageEntity::FORMAT_AVIF    => $frames->readAvif($handle),
                default                     => $frames->frames = 1
            };

        } finally {
            fclose($handle);
        }

        return $frames;
    }


    public function isAnimated() : bool { return $this->frames > 1; }

    public function getPixels() : int { return $this->canvasWidth * $this->canvasHeight * $this->frames; }


    protected function addFrame(int $left, int $top, int $width, int $height) : void
    {
        $this->frames++;

        if( $left + $width > $this->canvasWidth || $top + $height > $this->canvasHeight ) {
            $this->frameOutsideCanvas = true;
        }
    }


    /**
     * Walks the GIF blocks, skipping the LZW data sub-block by sub-block
     * 📚 https://www.w3.org/Graphics/GIF/spec-gif89a.txt
     *
     * @param resource $handle
     */
    protected function readGif($handle, int $stopAfter) : void
    {
        // header (6 bytes) + logical screen descriptor (7), then the optional global color table
        $header = fread($handle, 13);
        if( strlen($header) < 13 || !str_starts_with($header, 'GIF') ) {
            return;
        }

        ['width' => $this->canvasWidth, 'height' => $this->canvasHeight] = unpack('vwidth/vheight', $header, 6);
        $this->skipGifColorTable($handle, ord($header[10]));

        while( $this->frames < $stopAfter && ($block = fread($handle, 1)) !== false && $block !== '' ) {

            if( $block == "\x2C" ) {

                // image descriptor: left, top, width, height (2 bytes each) + packed fields
                $descriptor = fread($handle, 9);
                if( strlen($descriptor) < 9 ) {
                    break;
                }

                $rect = unpack('vleft/vtop/vwidth/vheight', $descriptor);
                $this->addFrame($rect['left'], $rect['top'], $rect['width'], $rect['height']);

                $this->skipGifColorTable($handle, ord($descriptor[8]));
                // LZW minimum code size, then the data sub-blocks
                fseek($handle, 1, SEEK_CUR);
                $this->skipGifSubBlocks($handle);

            } elseif( $block == "\x21" ) {

                // extension: label, then its sub-blocks
                fseek($handle, 1, SEEK_CUR);
                $this->skipGifSubBlocks($handle);

            } else {

                // 0x3B trailer, or garbage
                break;
            }
        }
    }


    /** @param resource $handle */
    protected function skipGifColorTable($handle, int $packedFields) : void
    {
        if( $packedFields & 0x80 ) {
            fseek($handle, 3 * (2 << ($packedFields & 0x07)), SEEK_CUR);
        }
    }


    /** @param resource $handle */
    protected function skipGifSubBlocks($handle) : void
    {
        while( ($size = fread($handle, 1)) !== false && $size !== '' && $size !== "\x00" ) {
            fseek($handle, ord($size), SEEK_CUR);
        }
    }


    /**
     * A WebP without the VP8X chunk (or without its "animation" flag) is a still: then the frames are its ANMF chunks
     * 📚 https://developers.google.com/speed/webp/docs/riff_container#extended_file_format
     *
     * @param resource $handle
     */
    protected function readWebp($handle, int $stopAfter) : void
    {
        $header = fread($handle, 30);
        if( strlen($header) < 16 || !str_starts_with($header, 'RIFF') || substr($header, 8, 4) != 'WEBP' ) {
            return;
        }

        if( substr($header, 12, 4) != 'VP8X' || strlen($header) < 30 || !(ord($header[20]) & 0x02) ) {
            $this->frames = 1;
            return;
        }

        // VP8X payload: flags (1 byte) + reserved (3) + canvas width - 1 (3) + canvas height - 1 (3)
        $this->canvasWidth  = static::uint24($header, 24) + 1;
        $this->canvasHeight = static::uint24($header, 27) + 1;

        // first chunk after VP8X (whose payload is 10 bytes)
        fseek($handle, 30);
        while( $this->frames < $stopAfter && strlen($chunkHeader = (string)fread($handle, 8)) == 8 ) {

            $fourCC = substr($chunkHeader, 0, 4);
            $length = unpack('V', $chunkHeader, 4)[1];

            if( $fourCC == 'ANMF' ) {

                if( $length < 12 ) {
                    break;
                }

                // frame X / 2, Y / 2, width - 1, height - 1: 3 bytes each
                $frameHeader = fread($handle, 12);
                if( strlen($frameHeader) < 12 ) {
                    break;
                }

                $this->addFrame(
                    static::uint24($frameHeader, 0) * 2, static::uint24($frameHeader, 3) * 2,
                    static::uint24($frameHeader, 6) + 1, static::uint24($frameHeader, 9) + 1
                );

                $length -= 12;
            }

            // chunk payloads are padded to an even size
            fseek($handle, $length + ($length % 2), SEEK_CUR);
        }
    }


    /**
     * APNG: an acTL chunk before the first IDAT; every frame is described by an fcTL chunk
     * 📚 https://www.w3.org/TR/png-3/#apng-frame-based-animation
     *
     * @param resource $handle
     */
    protected function readPng($handle, int $stopAfter) : void
    {
        if( fread($handle, 8) !== "\x89PNG\r\n\x1A\n" ) {
            return;
        }

        $declaredFrames = 0;
        $isApng         = false;
        $countedFrames  = 0;

        while( $countedFrames < $stopAfter && strlen($chunkHeader = (string)fread($handle, 8)) == 8 ) {

            $length = unpack('N', $chunkHeader)[1];
            $type   = substr($chunkHeader, 4, 4);
            $read   = 0;

            if( $type == 'IHDR' ) {

                $data = (string)fread($handle, 8);
                $read = strlen($data);
                if( $read == 8 ) {
                    ['width' => $this->canvasWidth, 'height' => $this->canvasHeight] = unpack('Nwidth/Nheight', $data);
                }

            } elseif( $type == 'acTL' ) {

                // only reached before the first IDAT: an acTL after it is not APNG
                $data = (string)fread($handle, 4);
                $read = strlen($data);
                $isApng = $read == 4;
                $declaredFrames = $isApng ? unpack('N', $data)[1] : 0;

            } elseif( $type == 'fcTL' && $isApng ) {

                // sequence number, width, height, x offset, y offset: 4 bytes each
                $data = (string)fread($handle, 20);
                $read = strlen($data);
                if( $read == 20 ) {
                    $rect = unpack('Nsequence/Nwidth/Nheight/Nleft/Ntop', $data);
                    $this->addFrame($rect['left'], $rect['top'], $rect['width'], $rect['height']);
                    $countedFrames++;
                }

            } elseif( $type == 'IDAT' && !$isApng ) {

                // a plain PNG: its single frame is the image
                $this->frames = 1;
                return;

            } elseif( $type == 'IEND' ) {

                break;
            }

            // a chunk shorter than the fields it must contain is malformed: never seek backwards
            if( $length < $read ) {
                break;
            }

            // rest of the chunk data + CRC
            fseek($handle, $length - $read + 4, SEEK_CUR);
        }

        // decoders trust acTL: a file declaring more frames than it describes must not slip under the limits
        $this->frames = max($this->frames, min($declaredFrames, $stopAfter));
    }


    /**
     * AVIF image sequence ("avis" brand): frames = samples of its video track, canvas = the track's sample entry
     * 📚 https://aomediacodec.github.io/av1-avif/#image-sequence-brand
     *
     * @param resource $handle
     */
    protected function readAvif($handle) : void
    {
        $header = fread($handle, 8);
        if( strlen($header) < 8 || substr($header, 4, 4) != 'ftyp' ) {
            return;
        }

        $ftypSize = unpack('N', $header)[1];
        if( $ftypSize < 16 || $ftypSize > 1024 ) {
            return;
        }

        // major brand, minor version, compatible brands: 4 bytes each
        $brands = str_split( (string)fread($handle, $ftypSize - 8), 4 );
        unset($brands[1]);

        // a still AVIF: its single frame is the primary image
        $this->frames = 1;
        if( !in_array('avis', $brands) ) {
            return;
        }

        // top-level boxes, up to moov
        $offset = $ftypSize;
        while( fseek($handle, $offset) === 0 && strlen($boxHeader = (string)fread($handle, 8)) == 8 ) {

            $boxSize = unpack('N', $boxHeader)[1];
            $type    = substr($boxHeader, 4, 4);

            if( $boxSize == 1 ) {
                $largeSize = (string)fread($handle, 8);
                $boxSize = strlen($largeSize) == 8 ? unpack('J', $largeSize)[1] : 0;
                $headerSize = 16;
            } else {
                $headerSize = 8;
            }

            if( $boxSize < $headerSize ) {
                return;
            }

            if( $type == 'moov' ) {

                if( $boxSize > static::AVIF_MOOV_MAX_BYTES ) {
                    return;
                }

                $this->readAvifTracks( (string)fread($handle, $boxSize - $headerSize) );
                return;
            }

            $offset += $boxSize;
        }
    }


    protected function readAvifTracks(string $moov) : void
    {
        foreach( static::isoChildren($moov, 'trak') as $trak ) {

            $stbl = static::isoPath($trak, ['mdia', 'minf', 'stbl']);
            if( $stbl === null ) {
                continue;
            }

            // stsz: version/flags (4), sample size (4), sample count (4)
            $stsz = static::isoChildren($stbl, 'stsz')[0] ?? '';
            // stsd: version/flags (4), entry count (4), then the visual sample entry: size (4), type (4),
            // reserved (6), data reference index (2), pre-defined + reserved (16), width (2), height (2) => offset 40
            $stsd = static::isoChildren($stbl, 'stsd')[0] ?? '';

            if( strlen($stsz) < 12 || strlen($stsd) < 44 ) {
                continue;
            }

            $samples = unpack('N', $stsz, 8)[1];
            ['width' => $width, 'height' => $height] = unpack('nwidth/nheight', $stsd, 40);

            // the colour track and the (optional) alpha track describe the same frames
            if( $samples >= $this->frames ) {
                $this->frames       = $samples;
                $this->canvasWidth  = $width;
                $this->canvasHeight = $height;
            }
        }
    }


    /**
     * The payloads of the direct children of an ISO-BMFF container payload having the given type
     *
     * @return string[]
     */
    protected static function isoChildren(string $payload, string $type) : array
    {
        $arrChildren = [];
        $offset = 0;
        $length = strlen($payload);

        while( $offset + 8 <= $length ) {

            $boxSize = unpack('N', $payload, $offset)[1];
            if( $boxSize < 8 || $offset + $boxSize > $length ) {
                break;
            }

            if( substr($payload, $offset + 4, 4) == $type ) {
                $arrChildren[] = substr($payload, $offset + 8, $boxSize - 8);
            }

            $offset += $boxSize;
        }

        return $arrChildren;
    }


    protected static function isoPath(string $payload, array $arrTypes) : ?string
    {
        foreach($arrTypes as $type) {

            $payload = static::isoChildren($payload, $type)[0] ?? null;
            if( $payload === null ) {
                return null;
            }
        }

        return $payload;
    }


    protected static function uint24(string $bytes, int $offset) : int
    {
        return ord($bytes[$offset]) | (ord($bytes[$offset + 1]) << 8) | (ord($bytes[$offset + 2]) << 16);
    }
}
