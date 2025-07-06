<?php
namespace App\ServiceCollection;

use App\Service\Factory;
use TurboLabIt\ServiceEntityPlusBundle\SEPCollection;


abstract class BaseServiceEntityCollection extends SEPCollection
{
    public function __construct(protected Factory $factory) { parent::__construct( $factory->getEntityManager() ); }


    /*
     🔥 Implement these methods as if they were uncommented! (contravariance in parameter make it undeclarable here)
    public function getRepository() : SpecificTypeRepository
    {
        return $this->factory->getEntityManager()->getRepository(static::ENTITY_CLASS);
    }
    */
}
