<?php
namespace App\ServiceCollection;

use App\Service\Factory;
use TurboLabIt\ServiceEntityPlusBundle\SEPCollection;


abstract class BaseServiceEntityCollection extends SEPCollection
{
    public function __construct(protected Factory $factory) { parent::__construct( $factory->getEntityManager() ); }

    // ⚠️ Additional methods to implement: https://github.com/TurboLabIt/php-symfony-service-entity-plus-bundle/blob/main/src/SEPCollection.php
}
