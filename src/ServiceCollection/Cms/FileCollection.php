<?php
namespace App\ServiceCollection\Cms;

use App\Service\Cms\File as FileService;
use App\Entity\Cms\File as FileEntity;
use App\ServiceCollection\BaseServiceEntityCollection;


class FileCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = FileService::ENTITY_CLASS;


    public function loadOrphans() : static
    {
        $arrEntities = $this->getRepository()->getOrphans();
        return $this->setEntities($arrEntities);
    }


    public function createService(?FileEntity $entity = null) : FileService
    {
        return $this->factory->createFile($entity);
    }
}
