<?php
namespace App\Trait;

use App\Service\User as UserService;


trait SecurityTrait
{
    public function getCurrentUser() : ?UserService { return $this->factory->getCurrentUser(); }
}
