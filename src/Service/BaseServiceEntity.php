<?php
namespace App\Service;


use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class BaseServiceEntity
{
    const ENTITY_CLASS          = null;
    const NOT_FOUND_EXCEPTION   = NotFoundHttpException::class;


    public function clear() : static
    {
        $entity = new (static::ENTITY_CLASS)();
        return $this->setEntity($entity);
    }


    public function load(int $id) : static
    {
        $this->clear();
        $entity = $this->em->getRepository(static::ENTITY_CLASS)->find($id);

        if( empty($entity) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($id);
        }

        return $this->setEntity($entity);
    }


    // 🔥 Implement these abstract method as if they were uncommented! (different types in signature make them unusable)
    // abstract public function setEntity(?BaseEntity $entity = null) : static;
    // abstract public function getEntity() : ?BaseEntity;

    public function getId() : ?int { return $this->entity->getId(); }
    public function getTitle() : ?string { return $this->entity->getTitle(); }
}
