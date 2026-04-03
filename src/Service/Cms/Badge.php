<?php
namespace App\Service\Cms;
use App\Entity\Cms\Badge as BadgeEntity;
use App\Exception\BadgeNotFoundException;
use App\Repository\Cms\BadgeRepository;
use App\Service\Factory;


class Badge extends BaseCmsService
{
    const string ENTITY_CLASS           = BadgeEntity::class;
    const string TLI_CLASS              = BadgeEntity::TLI_CLASS;
    const string NOT_FOUND_EXCEPTION    = BadgeNotFoundException::class;

    const int ID_AI = 21;

    protected BadgeEntity $entity;


    public function __construct(protected Factory $factory)
    {
        $this->clear();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : BadgeRepository
    {
        /** @var BadgeRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(BadgeEntity::class);
        return $repository;
    }

    public function setEntity(?BadgeEntity $entity = null) : static
    {
        $this->entity = $entity ?? new (static::ENTITY_CLASS)();
        return $this;
    }

    public function getEntity() : ?BadgeEntity { return $this->entity ?? null; }
    //</editor-fold>
}
