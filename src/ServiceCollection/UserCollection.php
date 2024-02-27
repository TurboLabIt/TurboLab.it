<?php
namespace App\ServiceCollection;

use App\Service\User as UserService;
use App\Entity\PhpBB\User as UserEntity;


class UserCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = UserService::ENTITY_CLASS;


    public function loadNewsletterTestRecipients() : static
    {
        // 👀 https://turbolab.it/forum/memberlist.php?mode=viewprofile&u=2
        $testUser = $this->em->getRepository(static::ENTITY_CLASS)->find(2);
        return $this->setEntities([$testUser]);
    }


    public function createService(?UserEntity $entity = null) : UserService { return $this->factory->createUser($entity); }
}
