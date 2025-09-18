<?php
namespace App\Service\Sentinel;

use App\Service\Factory;
use App\Service\User;


abstract class BaseSentinel
{
    public function __construct(protected Factory $factory) {}

    public function getCurrentUser() : ?User { return $this->factory->getCurrentUser(); }

    public function getCurrentUserAsAuthor() : ?User { return $this->factory->getCurrentUserAsAuthor(); }
}
