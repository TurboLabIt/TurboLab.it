<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Image;
use App\Service\Cms\Image as ImageService;
use App\ServiceCollection\BaseServiceEntityCollection;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md
 */
class ImageCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = ImageService::ENTITY_CLASS;

    public function get404() : ImageService
    {
        $entity =
            (new ImageEntity())
                ->setId(Image::ID_404)
                ->setFormat(ImageEntity::FORMAT_JPG);

        return $this->createService($entity);
    }


    public function createService(?ImageEntity $entity = null) : ImageService
        { return $this->factory->createImage($entity); }
}
