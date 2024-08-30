<?php
namespace App\Service\Cms;

use App\Entity\Cms\Image as ImageEntity;
use App\Exception\ImageLogicException;
use App\Exception\ImageNotFoundException;
use App\Repository\Cms\ImageRepository;
use App\Service\Factory;
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
    const string ENTITY_CLASS          = ImageEntity::class;
    const string NOT_FOUND_EXCEPTION   = 'App\Exception\ImageNotFoundException';

    const int ID_404                    = 24297;    // 👀 https://turbolab.it/immagini/24297/med
    const int ID_DEFAULT_SPOTLIGHT      = 1;        // 👀 https://turbolab.it/immagini/1/med

    const bool BUILD_CACHE_ENABLED      = true;
    const BUILD_FORMAT_FORCED           = null;

    const string WIDTH  = 'width';
    const string HEIGHT = 'height';

    const string WATERMARK_FILEPATH     = 'images/logo/turbolab.it.png';
    const int WATERMARK_WIDTH_PERCENT   = 25;
    const int WATERMARK_OPACITY         = 100;
    const int WATERMARK_MIN_SIZE        = 175;
    const array MIN_WATERMARKABLE_SIZES = [
        self::WIDTH     => 300,
        self::HEIGHT    => 300,
    ];

    const int HOW_MANY_FILES_PER_FOLDER = 5000;

    const string SIZE_MIN = 'min';
    const string SIZE_MED = 'med';
    const string SIZE_MAX = 'max';

    const array SIZE_DIMENSIONS = [
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

    const string UPLOADED_IMAGES_FOLDER_NAME = parent::UPLOADED_ASSET_FOLDER_NAME . "/images";

    protected ProjectDir $projectDir;
    protected ImageEntity $entity;
    protected static ?string $buildFileExtension    = null;
    protected ?string $lastBuiltImageMimeType       = null;


    public function __construct(protected Factory $factory)
    {
        $this->clear();
        $this->projectDir = $factory->getProjectDir();
        if( empty(static::$buildFileExtension) ) {
            static::$buildFileExtension = static::getClientSupportedBestFormat();
        }
    }

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : ImageRepository
    {
        /** @var ImageRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(ImageEntity::class);
        return $repository;
    }

    public function setEntity(?ImageEntity $entity = null) : static
    {
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?ImageEntity { return $this->entity ?? null; }
    //</editor-fold>


    public function getSizes() : array
        { return array_keys(static::SIZE_DIMENSIONS); }


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

        return implode('.', [$imageId, $format]);
    }


    public function getOriginalFilePath() : string
    {
        $imageFolderMod = $this->getFolderMod();
        $fileName       = $this->getOriginalFileName();
        return
            $this->projectDir->createVarDirFromFilePath(
                static::UPLOADED_IMAGES_FOLDER_NAME . "/originals/$imageFolderMod/$fileName"
            );
    }


    public function getFolderMod() : int
    {
        $imageId = $this->entity->getId();
        if( empty($imageId) ) {
            throw new ImageLogicException("Cannot get the FolderMod of an Image without ID");
        }

        return (int)ceil($imageId / static::HOW_MANY_FILES_PER_FOLDER);
    }


    public function getBuiltFileName() : string
    {
        $fileName = $this->getOriginalFileName();
        return pathinfo($fileName, PATHINFO_FILENAME) . "." . static::$buildFileExtension;
    }


    protected function getBuiltFilePath(string $size, bool $createBuildFolder) : string
    {
        $this->checkSize($size);

        $imageFolderMod     = $this->getFolderMod();
        $fileName           = $this->getBuiltFileName();
        $relativeFilePath   = static::UPLOADED_IMAGES_FOLDER_NAME . "/cache/$size/$imageFolderMod/$fileName";
        return
            $createBuildFolder
                ? $this->projectDir->createVarDirFromFilePath($relativeFilePath)
                : $this->projectDir->getVarDirFromFilePath($relativeFilePath);
    }


    public function tryPreBuilt(string $size) : bool
    {
        $builtFilePath = $this->getBuiltFilePath($size, false);
        $exists = file_exists($builtFilePath) && static::BUILD_CACHE_ENABLED;
        $this->lastBuiltImageMimeType = $exists ? mime_content_type($builtFilePath) : null;
        return $exists;
    }


    public function build(string $size) : static
    {
        $originalFilePath = $this->getOriginalFilePath();

        if( !file_exists($originalFilePath) ) {
            throw new ImageNotFoundException("Original image file not found! The expected path was ##$originalFilePath##");
        }

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

        // resize (down only, no upscaling)
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
                ->getBuiltFilePath($size, true);

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

        $watermarkPosition = $this->entity->getWatermarkPosition();

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

        return new Point($x, $y);
    }


    public function getBuiltImageMimeType() : ?string
    {
        return $this->lastBuiltImageMimeType;
    }


    public function getXSendPath(string $size) : string
    {
        return
            DIRECTORY_SEPARATOR .
            implode(DIRECTORY_SEPARATOR, [
                static::UPLOADED_ASSET_XSEND_PATH, 'images', 'cache', $size, $this->getFolderMod(), $this->getBuiltFileName()
            ]);
    }


    public function getContent(string $size) : string
    {
        $result = $this->tryPreBuilt($size);

        if( !$result ) {
            $this->build($size);
        }

        $filePath   = $this->getBuiltFilePath($size, false);
        return file_get_contents($filePath);
    }


    public static function getClientSupportedBestFormat() : string
    {
        if( !empty(static::BUILD_FORMAT_FORCED) ) {
            return static::BUILD_FORMAT_FORCED;
        }

        $httpAccept = $_SERVER['HTTP_ACCEPT'] ?? '';

        $arrImageFormats = ImageEntity::getFormats();
        foreach($arrImageFormats as $format) {

            $bestFormatMimeType = 'image/' . $format;
            $isSupported = stripos($httpAccept, $bestFormatMimeType) !== false;

            if($isSupported) {
                return $format;
            }
        }

        // the client doesn't support ANY graphic format! Maybe it's running from CLI?
        // let's just return the best format we have
        return reset($arrImageFormats);
    }


    public function getFormat() : ?string { return $this->entity->getFormat(); }

    public function getUrl(Article $article, string $size) : string
        { return $this->factory->getImageUrlGenerator()->generateUrl($this, $article, $size); }

    public function getShortUrl(string $size) : string
        { return $this->factory->getImageUrlGenerator()->generateShortUrl($this, $size); }
}
