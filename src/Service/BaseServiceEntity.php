<?php
namespace App\Service;

use App\Entity\BaseEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


abstract class BaseServiceEntity
{
    const ENTITY_CLASS          = null;
    const NOT_FOUND_EXCEPTION   = NotFoundHttpException::class;

    protected EntityManagerInterface $em;
    protected BaseEntity $entity;


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


    // 🔥 Implement this abstract method as if was uncommented! (contravariance in parameter make it undeclarable here)
    // abstract public function setEntity(?BaseEntity $entity = null) : static;
    abstract public function getEntity() : ?BaseEntity;

    public function getId() : ?int { return $this->entity->getId(); }
    public function getTitle() : ?string { return $this->entity->getTitle(); }
    public function getTitleFormatted() : ?string { return mb_ucfirst( $this->getTitle() ); }
    public function getCacheKey() : string
        { return strtolower( substr(strrchr(get_class($this), '\\'), 1) ) . "-" . $this->getId(); }
}
