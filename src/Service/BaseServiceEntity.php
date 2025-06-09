<?php
namespace App\Service;

use App\Entity\BaseEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * @property BaseEntity $entity
 */
abstract class BaseServiceEntity
{
    const ENTITY_CLASS          = null;
    const NOT_FOUND_EXCEPTION   = NotFoundHttpException::class;

    protected EntityManagerInterface $em;

    // this must be specialized in each Service with its own entity (ArticleEntity, FileEntity, ...)
    //protected BaseEntity $entity;

    public function clear() : static
    {
        $entity = new (static::ENTITY_CLASS)();
        return $this->setEntity($entity);
    }


    public function load(int $id) : static
    {
        $this->clear();
        $entity = $this->getRepository()->find($id);

        if( empty($entity) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($id);
        }

        return $this->setEntity($entity);
    }

    /*
     🔥 Implement these methods as if they were uncommented! (contravariance in parameter make it undeclarable here)
    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : SpecificTypeRepository
    {
        ** @var SpecificTypeRepository $repository *
        $repository = $this->factory->getEntityManager()->getRepository(SpecificTypeEntity::class);
        return $repository;
    }

    public function setEntity(?SpecificTypeEntity $entity = null) : static
    {
        if( property_exists($this, 'localViewCount') ) {
            $this->localViewCount = $entity->getViews();
        }

        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?SpecificTypeEntity { return $this->entity ?? null; }
    //</editor-fold>
    */

    public function getId() : ?int { return $this->entity->getId(); }

    public function getTitle() : ?string { return $this->entity->getTitle(); }

    public function getCacheKey() : string
        { return strtolower( substr(strrchr(get_class($this), '\\'), 1) ) . "-" . $this->getId(); }
}
