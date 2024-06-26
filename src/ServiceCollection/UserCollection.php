<?php
namespace App\ServiceCollection;

use App\Service\User;
use App\Entity\phpBB\User as UserEntity;


class UserCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = User::ENTITY_CLASS;
    protected array $arrTopEmailProviders = [];


    public function loadNewsletterTestRecipients() : static
    {
        $testUser = $this->em->getRepository(static::ENTITY_CLASS)->find(User::SYSTEM_USER_ID);
        return $this->setEntities([$testUser]);
    }


    public function loadNewsletterSubscribers() : static
    {
        $arrSubscribers = $this->em->getRepository(static::ENTITY_CLASS)->findNewsletterSubscribers();
        return $this->setEntities($arrSubscribers);
    }


    public function getTopEmailProviders(?int $limitTo = 3) : array
    {
        if( !empty($this->arrTopEmailProviders) ) {
            return $this->arrTopEmailProviders;
        }

        /** @var User $user */
        foreach($this as $user) {

            $email  = $user->getEmail();
            $domain = substr($email, strpos($email, "@") + 1);
            if( array_key_exists($domain, $this->arrTopEmailProviders) ) {
                $this->arrTopEmailProviders[$domain]++;
            } else {
                $this->arrTopEmailProviders[$domain] = 1;
            }
        }

        arsort($this->arrTopEmailProviders);

        if( !empty($limitTo) ) {
            $this->arrTopEmailProviders = array_slice($this->arrTopEmailProviders, 0, $limitTo);
        }

        $totalUsers = $this->count();

        foreach($this->arrTopEmailProviders as $key => $num) {
            $this->arrTopEmailProviders[$key] = [
                "total"         => $num,
                "percentage"    => round($num / $totalUsers * 100, 2 )
            ];
        }

        return $this->arrTopEmailProviders;
    }


    public function createService(?UserEntity $entity = null) : User { return $this->factory->createUser($entity); }
}
