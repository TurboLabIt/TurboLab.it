<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Image;
use App\Service\Cms\Image as ImageService;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md
 */
class ImageCollection extends BaseCmsServiceCollection
{
    const string ENTITY_CLASS   =  ImageService::ENTITY_CLASS;


    public function get404() : ImageService
    {
        $entity =
            (new ImageEntity())
                ->setId(Image::ID_404)
                ->setFormat(ImageEntity::FORMAT_JPG);

        $image404 = $this->createService($entity);
        return $image404;
    }


    public function createService(?ImageEntity $entity = null) : ImageService { return $this->factory->createImage($entity); }
}
