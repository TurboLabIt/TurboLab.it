<?php
namespace App\Entity\Cms;

use App\Entity\BaseEntity;
use App\Trait\IdableEntityTrait;
use App\Trait\TimestampableEntityTrait;


abstract class BaseCmsEntity extends BaseEntity { use IdableEntityTrait, TimestampableEntityTrait; }
