<?php
namespace App\Service\Cms;

use App\Entity\Cms\Image as ImageEntity;
use App\Exception\ImageLogicException;
use Doctrine\ORM\EntityManagerInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;


class Image extends BaseCmsService
{
    const UPLOADED_IMAGES_FOLDER_NAME = parent::UPLOADED_ASSET_FOLDER_NAME . "/images";

    const HOW_MANY_FILES_PER_FOLDER = 5000;

    const SIZE_MIN  = 'min';
    const SIZE_MED  = 'med';
    const SIZE_MAX  = 'max';

    const WIDTH     = 'width';
    const HEIGHT    = 'height';

    const SIZE_DIMENSIONS = [
        self::SIZE_MIN  => [
            self::WIDTH     => 2000,
            self::HEIGHT    => 2000,
        ],
        self::SIZE_MED  => [
            self::WIDTH     => 2000,
            self::HEIGHT    => 2000,
        ],
        self::SIZE_MAX  => [
            self::WIDTH     => 2000,
            self::HEIGHT    => 2000,
        ]
    ];

    protected ImageEntity $entity;
    protected ?string $lastBuiltImageMimeType = null;


    public function __construct(
        protected ImageUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected ProjectDir $projectDir
    )
    {
        $this->entity = new ImageEntity();
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


    public function getFileName() : string
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
        $fileName   = $this->getFileName();
        $imageId    = $this->entity->getId();

        $imageFolderMod = (int)ceil($imageId / static::HOW_MANY_FILES_PER_FOLDER);

        $imageFilePath = $this->projectDir->createVarDirFromFilePath(
            static::UPLOADED_IMAGES_FOLDER_NAME . "/originals/$imageFolderMod/$fileName"
        );

        return $imageFilePath;
    }


    protected function getBuiltImageFilePath(string $size) : string
    {
        $this->checkSize($size);

        $fileName   = $this->getFileName();
        $imageId    = $this->entity->getId();

        $imageFolderMod = (int)ceil($imageId / static::HOW_MANY_FILES_PER_FOLDER);

        $imageFilePath = $this->projectDir->createVarDirFromFilePath(
            static::UPLOADED_IMAGES_FOLDER_NAME . "/cache/$size/$imageFolderMod/$fileName"
        );

        return $imageFilePath;
    }


    public function tryPreBuilt(string $size) : bool
    {
        $builtFilePath = $this->getBuiltImageFilePath($size);
        $exists = file_exists($builtFilePath);
        $this->lastBuiltImageMimeType = $exists ? mime_content_type($builtFilePath) : null;
        return $exists;
    }


    public function build(string $size) : static
    {
        $originalFilePath = $this->getOriginalFilePath();

        // https://symfony.com/doc/current/the-fast-track/en/23-imagine.html#optimizing-images-with-imagine
        list($iwidth, $iheight) = getimagesize($originalFilePath);
        $ratio = $iwidth / $iheight;

        $width  = static::SIZE_DIMENSIONS[$size][static::WIDTH];
        $height = static::SIZE_DIMENSIONS[$size][static::HEIGHT];

        if ($width / $height > $ratio) {
            $width = $height * $ratio;
        } else {
            $height = $width / $ratio;
        }

        // https://github.com/php-imagine/Imagine
        $phpImagine = (new Imagine())->open($originalFilePath);
        $phpImagine->resize(new Box($width, $height));

        //
        $outputFilePath =
            $this
                ->applyWatermark($phpImagine)
                ->getBuiltImageFilePath($size);

        $phpImagine->save($outputFilePath, [
            'flatten' => false,
            'jpeg_quality' => '80',
            'png_compression_level' => 9
        ]);

        $this->lastBuiltImageMimeType = mime_content_type($outputFilePath);

        return $this;
    }


    public function applyWatermark(ImageInterface $phpImagine) : static
    {
        return $this;
    }


    public function getBuiltImageMimeType() : ?string
    {
        return $this->lastBuiltImageMimeType;
    }


    public function getXSendPath(string $size) : string
    {
        $imageId = $this->getId();
        $imageFolderMod = (int)ceil($imageId / static::HOW_MANY_FILES_PER_FOLDER);

        $xSendPath = DIRECTORY_SEPARATOR .
            implode(DIRECTORY_SEPARATOR, [
                static::UPLOADED_ASSET_XSEND_PATH, 'images', 'cache', $size, $imageFolderMod, $this->getFileName()
            ]);

        return $xSendPath;
    }


    public function getEntity() : ImageEntity { return $this->entity; }
    public function getTitle() : ?string { return $this->entity->getTitle(); }
    public function getFormat() : ?string { return $this->entity->getFormat(); }

    public function getUrl(string $size) : string { return $this->urlGenerator->generateUrl($this, $size); }
    public function getShortUrl(string $size) : string { return $this->urlGenerator->generateShortUrl($this, $size); }
}
