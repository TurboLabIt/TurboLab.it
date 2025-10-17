<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Image;
use App\Service\Cms\Image as ImageService;
use App\ServiceCollection\BaseServiceEntityCollection;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md
 */
class ImageCollection extends BaseImageCollection
{
    public function get404() : ImageService
    {
        $entity =
            (new ImageEntity())
                ->setId(Image::ID_404)
                ->setFormat(ImageEntity::FORMAT_PNG)
                ->setWatermarkPosition(ImageEntity::WATERMARK_DISABLED);

        return $this->createService($entity);
    }


    public function loadByHash(array|int $hashes) : static
    {
        $hashes = is_array($hashes) ? $hashes : [$hashes];
        $arrEntities = $this->em->getRepository(static::ENTITY_CLASS)->getByHash($hashes);
        return $this->setEntities($arrEntities);
    }


    public function loadOrphans() : static
    {
        $arrEntities = $this->em->getRepository(static::ENTITY_CLASS)->getOrphans();
        return $this->setEntities($arrEntities);
    }
}
