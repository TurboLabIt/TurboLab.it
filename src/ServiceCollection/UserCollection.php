<?php
namespace App\ServiceCollection;

use App\Repository\PhpBB\UserRepository;
use App\Service\User;
use App\Entity\PhpBB\User as UserEntity;


class UserCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = User::ENTITY_CLASS;

    protected array $arrTopEmailProviders = [];


    public function getRepository() : UserRepository
    {
        /** @var UserRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(UserEntity::class);
        return $repository;
    }


    public function loadNewsletterTestRecipients() : static
    {
        $arrTestUsers = [
            "system" => $this->getRepository()->find(User::SYSTEM_USER_ID)
        ];

        $arrTestAddresses = [
            // https://www.mail-tester.com/test-m8dwvnnnt
            "test-m8dwvnnnt@srv1.mail-tester.com",
            // https://mxtoolbox.com/deliverability
            "ping@tools.mxtoolbox.com",
            // https://app.mailgenius.com/spam-test/6e3913
            "test-6e3913@test.mailgenius.com",
            // https://www.lemwarm.com/deliverability-test
            "deliverability-test+g57u4j6806x1@lemwarm.com"
        ];

        foreach($arrTestAddresses as $address) {

            $arrTestUsers[$address] =
                (new UserEntity())
                    ->setId( rand(999999, PHP_INT_MAX) )
                    ->setUsername($address)
                    ->setEmail($address);
        }

        return $this->setEntities($arrTestUsers);
    }


    public function loadNewsletterSubscribers() : static
    {
        $arrSubscribers = $this->getRepository()->findNewsletterSubscribers();
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
