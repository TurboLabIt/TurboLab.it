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

        // ...the same, frame by frame, and the animation limits. Counting stops right after the frame cap
        $format = (string)$file->guessExtension();
        $frames = ImageFrames::read($file->getPathname(), $format, static::ANIMATED_FRAMES_MAX + 1);
        $this->assertWithinAnimationLimits($frames);

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

        $this
            ->setTitle($filename)
            ->entity
                ->setFormat($format)
                ->setAnimated( $frames->isAnimated() )
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
     * Reject a file whose frames overflow its canvas (a malformed file, or a "decompression bomb" hiding a huge
     * frame behind a small declared size), and an animated image beyond the animation limits. Like
     * assertWithinPixelCap(), this reads the file structure only, before anything decodes the pixels.
     */
    protected function assertWithinAnimationLimits(ImageFrames $frames) : static
    {
        if( $frames->frameOutsideCanvas ) {
            throw new UnprocessableEntityHttpException(
                "L'immagine è malformata: uno dei suoi fotogrammi esce dai bordi dell'immagine stessa"
            );
        }

        if( !$frames->isAnimated() ) {
            return $this;
        }

        $width  = $frames->canvasWidth;
        $height = $frames->canvasHeight;

        if( $width > static::RESOLUTION_MAX_ANIMATED || $height > static::RESOLUTION_MAX_ANIMATED ) {
            throw new UnprocessableEntityHttpException(
                "L'immagine animata è troppo grande: {$width}x{$height}px. Il massimo per le immagini animate è " .
                static::RESOLUTION_MAX_ANIMATED . "x" . static::RESOLUTION_MAX_ANIMATED . "px"
            );
        }

        if( $frames->frames > static::ANIMATED_FRAMES_MAX ) {
            throw new UnprocessableEntityHttpException(
                "L'immagine animata ha troppi fotogrammi: più di " . static::ANIMATED_FRAMES_MAX . ", che è il massimo"
            );
        }

        if( $frames->getPixels() > static::ANIMATED_PIXEL_BUDGET ) {

            $megapixels     = (int)ceil($frames->getPixels() / 1_000_000);
            $maxMegapixels  = static::ANIMATED_PIXEL_BUDGET / 1_000_000;
            throw new UnprocessableEntityHttpException(
                "L'immagine animata è troppo pesante da elaborare: {$width}x{$height}px per {$frames->frames} fotogrammi " .
                "fanno $megapixels megapixel, il massimo è $maxMegapixels. Riduci la risoluzione o il numero di fotogrammi"
            );
        }

        return $this;
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
