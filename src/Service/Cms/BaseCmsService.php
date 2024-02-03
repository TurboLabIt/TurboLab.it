<?php
namespace App\Service\Cms;

use App\Entity\BaseEntity;
use App\Service\BaseService;


abstract class BaseCmsService extends BaseService
{
    const ENTITY_CLASS          = null;
    const NOT_FOUND_EXCEPTION   = null;


    public function load(int $id) : static
    {
        $this->clear();
        $entity = $this->em->getRepository(static::ENTITY_CLASS)->find($id);

        if( empty($this->entity) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($id);
        }

        return $this->setEntity($entity);
    }


    public function loadBySlugDashId(string $slugDashId) : static
    {
        $entityId = substr($slugDashId, strrpos($slugDashId, '-') + 1);
        return $this->load($entityId);
    }


    public function clear() : static
    {
        $entity = new (static::ENTITY_CLASS)();
        return $this->setEntity($entity);
    }


    public function setEntity(BaseEntity $entity) : static
    {
        $this->entity = $entity;
        return $this;
    }


    public function getEntity() : BaseEntity { return $this->entity; }
    public function getId() : ?int { return $this->entity->getId(); }
}
