<?php
namespace App\ServiceCollection\Cms;

use App\ServiceCollection\BaseServiceCollection;


abstract class BaseCmsServiceCollection extends BaseServiceCollection
{
    const ENTITY_CLASS = null;


    public function load(array $arrIds) : array
    {
        $this->clear();

        $arrEntities = $this->em->getRepository(static::ENTITY_CLASS)->findBy(["id" => $arrIds], ["updatedAt" => 'DESC']);
        $this->setEntities($arrEntities);

        return $this->arrData;
    }


    public function setEntities(array $arrEntities) : static
    {
        foreach($arrEntities as $entity) {

            $id = (string)$entity->getId();
            $service = $this->createService($entity);
            $this->arrData[$id] = $service;
        }

        return $this;
    }


    // 🔥 Implement these abstract method as if they were uncommented! (different types in signature make them unusable)
    // abstract public function createService(?BaseEntity $entity = null) : BaseService;
}
