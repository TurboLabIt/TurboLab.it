<?php
namespace App\Entity\Cms;

use App\Entity\BaseEntity;
use App\Trait\IdableEntityTrait;
use Gedmo\Timestampable\Traits\TimestampableEntity;


abstract class BaseCmsEntity extends BaseEntity { use IdableEntityTrait, TimestampableEntity; }
