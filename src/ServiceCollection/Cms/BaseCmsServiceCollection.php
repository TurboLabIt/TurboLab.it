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
        $arrServices = $this->loadByIds([$entityId]);
        if( empty($arrServices) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($entityId);
        }

        $service = reset($arrServices);
        return $service;
    }


    public function loadBySlugDashId(string $slugDashId) : BaseService
    {
        $entityId = substr($slugDashId, strrpos($slugDashId, '-') + 1);
        return $this->loadById($entityId);
    }


    public function loadByIds(array $arrIds) : array
    {
        $this->clear();

        $arrEntities =
            $this->em->getRepository(static::ENTITY_CLASS)
                ->findBy(["id" => $arrIds], ["updatedAt" => 'DESC']);

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
