<?php
namespace App\Service\Cms;

use App\Entity\BaseEntity;
use App\Service\BaseService;


abstract class BaseCmsService extends BaseService
{
    public function getId() : ?int
    {
        return $this->entity->getId();
    }

    public function getEntity() : BaseEntity { return $this->entity; }

    public function setEntity(BaseEntity $entity) : static
    {
        $this->entity = $entity;
        return $this;
    }
}
