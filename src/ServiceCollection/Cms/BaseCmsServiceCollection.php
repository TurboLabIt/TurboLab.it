<?php
namespace App\ServiceCollection\Cms;

use App\ServiceCollection\BaseServiceEntityCollection;


abstract class BaseCmsServiceCollection extends BaseServiceEntityCollection
{
    public function load(array $arrIds) : array
    {
        $arrEntities = $this->em->getRepository(static::ENTITY_CLASS)->findMultiple($arrIds);
        return $this->setEntities($arrEntities)->arrData;
    }
}
