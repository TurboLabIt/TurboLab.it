<?php
namespace App\Service\Cms;

use App\Entity\BaseEntity;
use App\Entity\Cms\Image as ImageEntity;
use App\Exception\ImageLogicException;
use Doctrine\ORM\EntityManagerInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md
 */
class Image extends BaseCmsService
{
    const BUILD_CACHE_ENABLED       = true;
    const BUILD_FORMAT_FORCED       = null;

    const WIDTH     = 'width';
    const HEIGHT    = 'height';

    const WATERMARK_FILEPATH        = 'images/logo/turbolab.it.png';
    const WATERMARK_WIDTH_PERCENT   = 25;
    const WATERMARK_OPACITY         = 100;
    const WATERMARK_FORCED_POSITION = null;
    const WATERMARK_MIN_SIZE        = 175;
    const MIN_WATERMARKABLE_SIZES   = [
        self::WIDTH     => 250,
        self::HEIGHT    => 250,
    ];


    const HOW_MANY_FILES_PER_FOLDER = 5000;

    const SIZE_MIN  = 'min';
    const SIZE_MED  = 'med';
    const SIZE_MAX  = 'max';

    const SIZE_DIMENSIONS = [
        self::SIZE_MIN  => [
            self::WIDTH     => 480,
            self::HEIGHT    => 270,
        ],
        self::SIZE_MED  => [
            self::WIDTH     => 960,
            self::HEIGHT    => 540,
        ],
        self::SIZE_MAX  => [
            self::WIDTH     => 1920,
            self::HEIGHT    => 1080,
        ]
    ];

    const UPLOADED_IMAGES_FOLDER_NAME = parent::UPLOADED_ASSET_FOLDER_NAME . "/images";

    protected ImageEntity $entity;
    protected static ?string $buildFileExtension    = null;
    protected ?string $lastBuiltImageMimeType       = null;


    public function __construct(
        protected ImageUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected ProjectDir $projectDir
    )
    {
        $this->entity = new ImageEntity();
        if( empty(static::$buildFileExtension) ) {
            static::setBestFormatFromBrowserSupport();
        }
    }


    public function getSizes() : array
    {
        return [static::SIZE_MIN, static::SIZE_MED, static::SIZE_MAX];
    }


    public function checkSize(string $size) : static
    {
        if( !in_array($size, $this->getSizes()) ) {
            throw new ImageLogicException("Unknown image size");
        }

        return $this;
    }


    public function getOriginalFileName() : string
    {
        $imageId = $this->entity->getId();
        if( empty($imageId) ) {
            throw new ImageLogicException("Cannot get the name of an Image without ID");
        }

        $format = $this->entity->getFormat();
        if( empty($format) ) {
            throw new ImageLogicException("Cannot get the name of an Image without format");
        }

        $imageFileName = implode('.', [$imageId, $format]);
        return $imageFileName;
    }


    public function getOriginalFilePath() : string
    {
        $imageFolderMod = $this->getFolderMod();
        $fileName       = $this->getOriginalFileName();
        $imageFilePath  = $this->projectDir->createVarDirFromFilePath(
            static::UPLOADED_IMAGES_FOLDER_NAME . "/originals/$imageFolderMod/$fileName"
        );

        return $imageFilePath;
    }


    public function getFolderMod() : int
    {
        $imageId = $this->entity->getId();
        if( empty($imageId) ) {
            throw new ImageLogicException("Cannot get the FolderMod of an Image without ID");
        }

        $imageFolderMod = (int)ceil($imageId / static::HOW_MANY_FILES_PER_FOLDER);
        return $imageFolderMod;
    }


    public function getBuiltFileName() : string
    {
        $fileName = $this->getOriginalFileName();

        if( !empty(static::$buildFileExtension) ) {
            $fileName = pathinfo($fileName, PATHINFO_FILENAME) . "." . static::$buildFileExtension;
        }

        return $fileName;
    }


    protected function getBuiltFilePath(string $size) : string
    {
        $this->checkSize($size);

        $imageFolderMod = $this->getFolderMod();
        $fileName       = $this->getBuiltFileName();

        $imageFilePath = $this->projectDir->createVarDirFromFilePath(
            static::UPLOADED_IMAGES_FOLDER_NAME . "/cache/$size/$imageFolderMod/$fileName"
        );

        return $imageFilePath;
    }


    public function tryPreBuilt(string $size) : bool
    {
        $builtFilePath = $this->getBuiltFilePath($size);
        $exists = file_exists($builtFilePath) && static::BUILD_CACHE_ENABLED;
        $this->lastBuiltImageMimeType = $exists ? mime_content_type($builtFilePath) : null;
        return $exists;
    }


    public function build(string $size) : static
    {
        $originalFilePath = $this->getOriginalFilePath();

        // 📚 https://symfony.com/doc/current/the-fast-track/en/23-imagine.html#optimizing-images-with-imagine
        // 📚 https://github.com/php-imagine/Imagine
        $phpImagine = (new Imagine())->open($originalFilePath);

        list($iwidth, $iheight) = getimagesize($originalFilePath);
        $ratio = $iwidth / $iheight;

        $width  = static::SIZE_DIMENSIONS[$size][static::WIDTH];
        $height = static::SIZE_DIMENSIONS[$size][static::HEIGHT];

        if ($width / $height > $ratio) {
            $width = $height * $ratio;
        } else {
            $height = $width / $ratio;
        }

        $width  = (int)round($width);
        $height = (int)round($height);

        // resize (down only, never "up")
        if($iwidth > $width || $iheight > $height ) {

            $phpImagine->resize(new Box($width, $height), ImageInterface::FILTER_MITCHELL);

        } else {

            // "resizing" the image to... the same size prevents the watermark to become "very black"
            $phpImagine->resize(new Box($iwidth, $iheight));
        }

        //
        $outputFilePath =
            $this
                ->applyWatermark($phpImagine, $size)
                ->getBuiltFilePath($size);

        $phpImagine->save($outputFilePath, [
            'flatten'               => true,
            'jpeg_quality'          => '80',
            'png_compression_level' => 9,
            'avif_quality'          => 40,
        ]);

        $this->lastBuiltImageMimeType = mime_content_type($outputFilePath);

        return $this;
    }


    public function applyWatermark(ImageInterface $phpImagine, string $size) : static
    {
        $this->checkSize($size);

        $watermarkPosition = static::WATERMARK_FORCED_POSITION ?? $this->entity->getWatermarkPosition();

        $imageW = $phpImagine->getSize()->getWidth();
        $imageH = $phpImagine->getSize()->getHeight();

        if(
            in_array($size, [static::SIZE_MIN]) ||
            $watermarkPosition == ImageEntity::WATERMARK_DISABLED ||
            $imageW < static::MIN_WATERMARKABLE_SIZES[static::WIDTH] ||
            $imageH < static::MIN_WATERMARKABLE_SIZES[static::HEIGHT]
        ) {
            return $this;
        }

        $watermarkFilePath = $this->projectDir->getPublicDirFromFilePath(static::WATERMARK_FILEPATH);

        $watermark  = (new Imagine())->open($watermarkFilePath);
        $watermW    = $watermark->getSize()->getWidth();
        $watermH    = $watermark->getSize()->getHeight();

        $newWatermW = floor( $imageW / 100 * static::WATERMARK_WIDTH_PERCENT );
        $newWatermW = (int)round($newWatermW);

        if( $newWatermW < static::WATERMARK_MIN_SIZE ) {
            $newWatermW = static::WATERMARK_MIN_SIZE;
        }

        $newWatermH = floor( $watermH *  $newWatermW / $watermW );
        $newWatermH = (int)round($newWatermH);

        /**
         * Mitchell is often the most accurate.
         * @link https://help.autodesk.com/view/ACD/2015/ENU/?guid=GUID-B3BF7F3A-CD5B-46D6-AC89-0BF9AEF27C47
         */
        $watermark->resize(new Box($newWatermW, $newWatermH), ImageInterface::FILTER_MITCHELL);

        $pointPosition = $this->getWatermarkPointPosition($watermarkPosition, $imageW, $imageH, $newWatermW, $newWatermH);

        $phpImagine->paste($watermark, $pointPosition, static::WATERMARK_OPACITY);
        return $this;
    }


    public function getWatermarkPointPosition(int $watermarkPosition, int $imageW, int $imageH, int $wmW, int $wmH) : Point
    {
        $x = 0;
        $y = 0;

        switch($watermarkPosition) {

            case ImageEntity::WATERMARK_TOP_LEFT: {

                // 0,0 is fine

            } break;

            case ImageEntity::WATERMARK_TOP_RIGHT: {

                $x = $imageW - $wmW;

            } break;

            case ImageEntity::WATERMARK_BOTTOM_RIGHT: {

                $x = $imageW - $wmW;
                $y = $imageH - $wmH;

            } break;

            case ImageEntity::WATERMARK_BOTTOM_LEFT: {

                $y = $imageH - $wmH;

            } break;

            default: {
                throw new ImageLogicException("Unhandled watermark position");
            }
        }

        $point = new Point($x, $y);
        return $point;
    }


    public function getBuiltImageMimeType() : ?string
    {
        return $this->lastBuiltImageMimeType;
    }


    public function getXSendPath(string $size) : string
    {
        $xSendPath =
            DIRECTORY_SEPARATOR .
            implode(DIRECTORY_SEPARATOR, [
                static::UPLOADED_ASSET_XSEND_PATH, 'images', 'cache', $size, $this->getFolderMod(), $this->getBuiltFileName()
            ]);

        return $xSendPath;
    }


    public function getContent(string $size) : string
    {
        $result = $this->tryPreBuilt($size);

        if( !$result ) {
            $this->build($size);
        }

        $filePath   = $this->getBuiltFilePath($size);
        $data       = file_get_contents($filePath);
        return $data;
    }


    public static function setBestFormatFromBrowserSupport() : ?string
    {
        if( !empty(static::BUILD_FORMAT_FORCED) ) {
            return static::$buildFileExtension = static::BUILD_FORMAT_FORCED;
        }

        $httpAccept = $_SERVER['HTTP_ACCEPT'] ?? '';

        foreach([ImageEntity::FORMAT_AVIF, ImageEntity::FORMAT_WEBP] as $format) {

            $bestFormatMimeType = 'image/' . $format;
            $isSupported = stripos($httpAccept, $bestFormatMimeType) !== false;

            if($isSupported) {
                return static::$buildFileExtension = $format;
            }
        }

        return static::$buildFileExtension = null;
    }


    public function getEntity() : ImageEntity { return parent::getEntity(); }

    /**
     * @param ImageEntity $entity
     */
    public function setEntity(BaseEntity $entity) : static { return parent::setEntity($entity); }

    public function getTitle() : ?string { return $this->entity->getTitle(); }
    public function getFormat() : ?string { return $this->entity->getFormat(); }

    public function getUrl(string $size) : string { return $this->urlGenerator->generateUrl($this, $size); }
    public function getShortUrl(string $size) : string { return $this->urlGenerator->generateShortUrl($this, $size); }
}
