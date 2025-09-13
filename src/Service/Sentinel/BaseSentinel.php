<?php
namespace App\Service\Sentinel;

use App\Service\Factory;
use App\Service\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


abstract class BaseSentinel
{
    public function __construct(protected Factory $factory) {}

    public function getCurrentUser() : ?User { return $this->factory->getCurrentUser(); }

    public function getCurrentUserAsAuthor() : ?User { return $this->factory->getCurrentUserAsAuthor(); }

    public function enforceLoggedUserOnly(string $errorMessage = "You're not logged in!") : static
    {
        if( empty( $this->getCurrentUser() ) ) {
            throw new AccessDeniedException($errorMessage);
        }

        return $this;
    }
}
