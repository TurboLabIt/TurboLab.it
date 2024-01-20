<?php
namespace App\ServiceCollection\Cms;

use App\Entity\BaseEntity;
use App\Service\BaseService;
use App\ServiceCollection\BaseServiceCollection;


abstract class BaseCmsServiceCollection extends BaseServiceCollection
{
    const ENTITY_CLASS          = null;
    const NOT_FOUND_EXCEPTION   = null;


    public function loadById(int $entityId) : BaseService
    {
        $this->clear();

        $entity = $this->em->getRepository(static::ENTITY_CLASS)->find($entityId);

        if( empty($entity) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($entityId);
        }

        $service    = $this->createService($entity);
        $id         = (string)$entity->getId();
        $this->arrData[$id] = $service;

        return $service;
    }


    public function loadBySlugDashId(string $slugDashId) : BaseService
    {
        $entityId = substr($slugDashId, strrpos($slugDashId, '-') + 1);
        return $this->loadById($entityId);
    }



    public function createService(?BaseEntity $entity = null) : BaseService
    {
        return $this->factory->create($entity);
    }
}
