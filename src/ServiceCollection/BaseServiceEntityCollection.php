<?php
namespace App\ServiceCollection;

use App\Service\Factory;
use Doctrine\ORM\EntityManagerInterface;
use TurboLabIt\ServiceEntityPlusBundle\SEPCollection;


abstract class BaseServiceEntityCollection extends SEPCollection
{
    public function __construct(protected EntityManagerInterface $em, protected Factory $factory) {}
}
