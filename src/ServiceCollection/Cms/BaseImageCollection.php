<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Image as ImageEntity;
use App\Repository\Cms\ImageRepository;
use App\Service\Cms\Image;
use App\ServiceCollection\BaseServiceEntityCollection;


abstract class BaseImageCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = Image::ENTITY_CLASS;

    public function getRepository() : ImageRepository { return $this->em->getRepository(static::ENTITY_CLASS); }

    public function createService(?ImageEntity $entity = null) : Image { return $this->factory->createImage($entity); }
}
