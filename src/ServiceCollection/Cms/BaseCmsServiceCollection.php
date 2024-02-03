<?php
namespace App\ServiceCollection\Cms;

use App\Entity\BaseEntity;
use App\Service\BaseService;
use App\ServiceCollection\BaseServiceCollection;


abstract class BaseCmsServiceCollection extends BaseServiceCollection
{
    const ENTITY_CLASS = null;


    public function load(array $arrIds) : array
    {
        $this->clear();

        $arrEntities = $this->em->getRepository(static::ENTITY_CLASS)->findBy(["id" => $arrIds], ["updatedAt" => 'DESC']);
        foreach($arrEntities as $entity) {

            $service    = $this->createService($entity);
            $id         = (string)$entity->getId();
            $this->arrData[$id] = $service;
        }

        return $this->arrData;
    }


    public function createService(?BaseEntity $entity = null) : BaseService
    {
        return $this->factory->create($entity);
    }
}
