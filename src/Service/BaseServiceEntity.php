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

    public function getTitle() : ?string
    {
        // this will return: Come mostrare un "messaggio" con 'JS' – <script>alert("bòòm");</script>
        $processing = $this->entity->getTitle();

        // this will return: Come mostrare un "messaggio" con 'JS' - &lt;script&gt;alert("bòòm");&lt;/script&gt;
        return htmlspecialchars($processing, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
    }


    protected function encodeTextForHTMLAttribute(?string $html) : ?string
    {
        if( empty($html) ) {
            return $html;
        }

        $processing = HtmlProcessorBase::decode($html);
        return htmlspecialchars($processing, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }


    public function getClass() : string { return static::TLI_CLASS; }

    public function getCacheKey() : string { return $this->getClass() . "-" . $this->getId(); }

    public function getTitleComparable() : string { return $this->entity->getTitleComparable(); }
}
