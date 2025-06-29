<?php
namespace App\Trait;

use App\Service\User;
use App\Service\User as UserService;


trait SecurityTrait
{
    public function getCurrentUser() : ?UserService { return $this->factory->getCurrentUser(); }


    public function currentUserCanEdit() : bool
    {
        $currentUser = $this->getCurrentUser();

        if( empty($currentUser) ) {
            return false;
        }

        return $this->isAuthor($currentUser) || $currentUser->isEditor();
    }


    public function isAuthor(?User $user = null) : bool
    {
        if( empty($user) ) {
            return false;
        }

        $arrAuthors = $this->getAuthors();
        return array_key_exists($user->getId(), $arrAuthors);
    }


    public function getAuthors() : array
    {
        if( is_array($this->arrAuthors) ) {
            return $this->arrAuthors;
        }

        $this->arrAuthors = [];

        $authorJunctionEntities = $this->entity->getAuthors();
        foreach($authorJunctionEntities as $junctionEntity) {

            $userEntity                     = $junctionEntity->getUser();
            $authorId                       = $userEntity->getId();
            $this->arrAuthors[$authorId]    = $this->factory->createUser($userEntity);
        }

        return $this->arrAuthors;
    }


    public function getAuthorsNotSystem() : array
    {
        return array_filter($this->getAuthors(), fn(User $user) => !$user->isSystem());
    }
}
