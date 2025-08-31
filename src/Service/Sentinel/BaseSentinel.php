<?php
namespace App\Service\Sentinel;

use App\Service\Factory;
use App\Service\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


abstract class BaseSentinel
{
    protected User $currentUserAsAuthor;

    public function __construct(protected Factory $factory) {}

    public function getCurrentUser() : ?User { return $this->factory->getCurrentUser(); }


    public function enforceLoggedUserOnly(string $errorMessage = "You're not logged in!") : static
    {
        if( empty( $this->getCurrentUser() ) ) {
            throw new AccessDeniedException($errorMessage);
        }

        return $this;
    }


    public function getCurrentUserAsAuthor() : ?User
    {
        /**
         * $currentUser is unknown to Doctrine: if we try to set it as Author directly:
         *
         * A new entity was found through the relationship 'App\Entity\Cms\ArticleAuthor#user' that was not configured
         *   to cascade persist operations for entity: App\Entity\PhpBB\User@--
         */

        if( !empty($this->currentUserAsAuthor) ) {
            return $this->currentUserAsAuthor;
        }

        $currentUserId = $this->getCurrentUser()?->getId();

        if( empty($currentUserId) ) {
            return null;
        }

        return $this->factory->createUser()->load($currentUserId);
    }
}
