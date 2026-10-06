<?php
namespace App\Service\Cms;

use App\Entity\Cms\Image as ImageEntity;
use App\Entity\Cms\ImageAuthor;
use App\Service\Factory;
use App\Service\TextProcessor;
use App\Service\User;
use App\Trait\SaveableTrait;
use App\Trait\UploadableFileTrait;
use DateTime;
use Imagine\Exception\NotSupportedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;


class ImageEditor extends Image
{
    use SaveableTrait, UploadableFileTrait;


    public function __construct(Factory $factory, protected TextProcessor $textProcessor)
    {
        parent::__construct($factory);
    }


    public function setTitle(string $newTitle) : static
    {
        $cleanTitle = $this->textProcessor->processRawInputTitleForStorage($newTitle);
        $this->entity->setTitle($cleanTitle);
        return $this;
    }


    public function createFromUploadedFile(UploadedFile $file) : ImageEditor
    {
        if( !str_starts_with($file->getMimeType(), 'image') ) {
            throw new NotSupportedException('The MIME is not image/*');
        }

        // general validation (from UploadableFileTrait)
        $this->validateUploadedFile($file);

        // reject oversized originals BEFORE storing/decoding them (decompression-bomb guard, #32)
        $this->assertWithinPixelCap( $file->getPathname() );

        $hash = hash_file('md5', $file->getPathname() );

        // handle "no-watermark" in filename
        $filename = preg_replace('/no[-\._]?watermark/i', '', $file->getClientOriginalName(), -1, $count);
        $filename = trim($filename);
        $noWatermark = $count > 0;
        if( $noWatermark || stripos($filename, 'logo') !== false || stripos($filename, 'spotlight') !== false ) {
            $this->entity->setWatermarkPosition(ImageEntity::WATERMARK_DISABLED);
        }

        if( empty($filename) ) {
            $filename = 'image-' . (new DateTime())->format('Y-m-d H:i:s') . '-' . rand(0, PHP_INT_MAX);
        }

        $format = $file->guessExtension();

        $this
            ->setTitle($filename)
            ->entity
                ->setFormat($format)
                ->setAnimated( $this->isAnimatedFile($file->getPathname(), $format) )
                ->setHash($hash);

        $this->save();

        $destinationFullPath = $this->getOriginalFilePath();

        $file->move( dirname($destinationFullPath), basename($destinationFullPath) );

        return $this;
    }


    /**
     * Reject an uploaded image whose width or height exceeds Image::RESOLUTION_MAX. Dimensions are
     * read from the file header with getimagesize() — which does NOT decode the pixel data — so an
     * oversized "decompression bomb" is refused here, at the upload trust boundary, before it is
     * stored and before build() ever opens it with GD (security-audit #32). Because the cap is
     * enforced on upload, build() does not re-check it.
     */
    protected function assertWithinPixelCap(string $filePath) : static
    {
        $imageSize = getimagesize($filePath);

        if( $imageSize === false ) {
            throw new UnprocessableEntityHttpException("Could not read the image dimensions");
        }

        [$width, $height] = $imageSize;

        if( $width > static::RESOLUTION_MAX || $height > static::RESOLUTION_MAX ) {
            throw new UnprocessableEntityHttpException(
                "Image too large: {$width}x{$height}px exceeds the maximum of " .
                static::RESOLUTION_MAX . "x" . static::RESOLUTION_MAX . "px"
            );
        }

        return $this;
    }


    /**
     * Whether the file holds more than one frame. Only the container structure is read, never the pixels:
     * this runs on untrusted uploads, before anything decodes them (see assertWithinPixelCap()).
     */
    protected function isAnimatedFile(string $filePath, string $format) : bool
    {
        $handle = fopen($filePath, 'rb');
        if( $handle === false ) {
            return false;
        }

        try {
            return match($format) {
                ImageEntity::FORMAT_GIF     => $this->countGifFrames($handle, 2) > 1,
                ImageEntity::FORMAT_WEBP    => $this->isAnimatedWebp($handle),
                ImageEntity::FORMAT_PNG     => $this->isAnimatedPng($handle),
                ImageEntity::FORMAT_AVIF    => $this->isAnimatedAvif($handle),
                default                     => false
            };

        } finally {
            fclose($handle);
        }
    }


    /**
     * Walks the GIF blocks, skipping the LZW data sub-block by sub-block, until $stopAt frames are found
     * 📚 https://www.w3.org/Graphics/GIF/spec-gif89a.txt
     *
     * @param resource $handle
     */
    protected function countGifFrames($handle, int $stopAt) : int
    {
        // header (6 bytes) + logical screen descriptor (7), then the optional global color table
        $header = fread($handle, 13);
        if( strlen($header) < 13 || !str_starts_with($header, 'GIF') ) {
            return 0;
        }

        $this->skipGifColorTable($handle, ord($header[10]));

        $frames = 0;
        while( $frames < $stopAt && ($block = fread($handle, 1)) !== false && $block !== '' ) {

            if( $block == "\x2C" ) {

                // image descriptor: left, top, width, height (2 bytes each) + packed fields
                $descriptor = fread($handle, 9);
                if( strlen($descriptor) < 9 ) {
                    break;
                }

                $frames++;
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

        return $frames;
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
     * The VP8X chunk, when present, is always the first one: its "animation" flag is authoritative
     * 📚 https://developers.google.com/speed/webp/docs/riff_container#extended_file_format
     *
     * @param resource $handle
     */
    protected function isAnimatedWebp($handle) : bool
    {
        $header = fread($handle, 21);
        return
            strlen($header) == 21 && str_starts_with($header, 'RIFF') &&
            substr($header, 8, 8) == 'WEBPVP8X' && (ord($header[20]) & 0x02);
    }


    /**
     * APNG: an acTL chunk before the first IDAT, declaring more than one frame
     * 📚 https://www.w3.org/TR/png-3/#apng-frame-based-animation
     *
     * @param resource $handle
     */
    protected function isAnimatedPng($handle) : bool
    {
        if( fread($handle, 8) !== "\x89PNG\r\n\x1A\n" ) {
            return false;
        }

        while( strlen($chunkHeader = (string)fread($handle, 8)) == 8 ) {

            ['length' => $length] = unpack('Nlength', $chunkHeader);
            $type = substr($chunkHeader, 4, 4);

            if( $type == 'acTL' ) {
                $numFrames = fread($handle, 4);
                return strlen($numFrames) == 4 && unpack('N', $numFrames)[1] > 1;
            }

            if( $type == 'IDAT' || $type == 'IEND' ) {
                return false;
            }

            // chunk data + CRC
            fseek($handle, $length + 4, SEEK_CUR);
        }

        return false;
    }


    /**
     * AVIF image sequence: "avis" among the brands of the leading ftyp box
     * 📚 https://aomediacodec.github.io/av1-avif/#image-sequence-brand
     *
     * @param resource $handle
     */
    protected function isAnimatedAvif($handle) : bool
    {
        $header = fread($handle, 8);
        if( strlen($header) < 8 || substr($header, 4, 4) != 'ftyp' ) {
            return false;
        }

        $boxSize = unpack('N', $header)[1];
        if( $boxSize < 16 || $boxSize > 1024 ) {
            return false;
        }

        // major brand, minor version, compatible brands: 4 bytes each
        $brands = str_split( (string)fread($handle, $boxSize - 8), 4 );
        unset($brands[1]);
        return in_array('avis', $brands);
    }


    public function addAuthor(User $author) : static
    {
        $this->entity->addAuthor(
            (new ImageAuthor())
                ->setUser( $author->getEntity() )
        );

        return $this;
    }


    public function setWatermarkPosition(int $position) : static
    {
        $this->entity->setWatermarkPosition($position);
        return $this->clearCached();
    }


    public function delete(bool $persist = true) : void
    {
        $this->clearCached();

        $filePath = $this->getOriginalFilePath();
        unlink($filePath);

        $em = $this->factory->getEntityManager();
        $em->remove($this->entity);

        if($persist) {
            $em->flush();
        }

        $this->clear();
    }


    public function clearCached() : static
    {
        // every format, not just the one of the current request: the cache holds whatever was requested
        // (AVIF on the web, PNG for og:image/newsletter, ...), and nginx serves any leftover file directly
        foreach(static::SIZES as $size) {
            foreach(ImageEntity::getFormats() as $format) {

                $filePath = $this->getBuiltFilePath($size, false, $format);
                if( file_exists($filePath) ) {
                    unlink($filePath);
                }
            }
        }

        return $this;
    }
}
