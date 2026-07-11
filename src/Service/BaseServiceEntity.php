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
    const string TLI_CLASS      = 'base';
    const NOT_FOUND_EXCEPTION   = NotFoundHttpException::class;

    protected EntityManagerInterface $em;
    // this must be specialized in each Service with its own entity (ArticleEntity, FileEntity, ...)
    //protected BaseEntity $entity;


    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    /*
     🔥 Implement these methods as if they were uncommented! (contravariance in parameter make it undeclarable here)
    public function getRepository() : SpecificTypeRepository { return $this->em->getRepository(SpecificTypeEntity::class); }


    public function setEntity(?SpecificTypeEntity $entity = null) : static
    {
        if( property_exists($this, 'localViewCount') ) {
            $this->localViewCount = $entity?->getViews() ?? 0;
        }

        $this->entity = $entity ?? new (static::ENTITY_CLASS)();
        return $this;
    }


    public function getEntity() : ?SpecificTypeEntity { return $this->entity ?? null; }
    */


    public function clear() : static
    {
        $entity = new (static::ENTITY_CLASS)();
        return $this->setEntity($entity);
    }


    public function load(int $id) : static
    {
        $this->clear();

        $exceptionClass = static::NOT_FOUND_EXCEPTION;

        if($id < 1) {
            throw new $exceptionClass($id);
        }

        $entity = $this->getRepository()->find($id);

        if( empty($entity) ) {
            throw new $exceptionClass($id);
        }

        return $this->setEntity($entity);
    }
    //</editor-fold>


    public function getClass() : string { return static::TLI_CLASS; }

    public function getId() : ?int { return $this->entity->getId(); }

    public function getCacheKey() : string { return $this->getClass() . "-" . $this->getId(); }


    //<editor-fold defaultstate="collapsed" desc="*** 🔖 Title ***">
    public function getTitle() : ?string
    {
        // this will return: Come mostrare un "messaggio" con 'JS' – <script>alert("bòòm");</script>
        return $this->entity->getTitle();
    }


    public function getTitleComparable() : string { return $this->entity->getTitleComparable(); }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 👟 Object cache (stored on the entity) ***">
    public function setCachedData(string $cacheKey, mixed $data) : static
    {
        $this->entity->setCachedData($cacheKey, $data);
        return $this;
    }

    public function isCachedData(string $cacheKey) : bool { return $this->entity->isCachedData($cacheKey); }

    public function getCachedData(string $cacheKey) : mixed { return $this->entity->getCachedData($cacheKey); }
    //</editor-fold>
}
