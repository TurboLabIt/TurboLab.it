<?php
namespace App\ServiceCollection;

use App\Service\Factory;
use TurboLabIt\ServiceEntityPlusBundle\SEPCollection;


abstract class BaseServiceEntityCollection extends SEPCollection
{
    public function __construct(protected Factory $factory)
        { parent::__construct( $factory->getEntityManager() ); }
}
