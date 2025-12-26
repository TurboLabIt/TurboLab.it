<?php
namespace App\ServiceCollection;

use App\Repository\PhpBB\UserRepository;
use App\Service\User;
use App\Entity\PhpBB\User as UserEntity;


class UserCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = User::ENTITY_CLASS;

    protected array $arrTopEmailProviders = [];


    public function getRepository() : UserRepository { return parent::getRepository(); }


    public function loadNewsletterTestRecipients() : static
    {
        $arrTestUsers = [
            "system"            => $this->getRepository()->find(User::ID_SYSTEM),
            'tli-dev-libero'    => $this->getRepository()->find(7238),
            'tli-dev-outlook'   => $this->getRepository()->find(7239),
        ];

        $arrTestAddresses = [
            // https://www.mail-tester.com/test-m8dwvnnnt
            "test-m8dwvnnnt@srv1.mail-tester.com",
            // https://mxtoolbox.com/deliverability
            "ping@tools.mxtoolbox.com",
            // https://app.mailgenius.com/spam-test/6e3913
            //"test-6e3913@test.mailgenius.com",
            // https://www.lemwarm.com/deliverability-test
            //"deliverability-test+g57u4j6806x1@lemwarm.com"
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


    public function loadBySearchUsername(string $username) : static
    {
        $arrUsers = $this->getRepository()->searchByUsername($username);
        return $this->setEntities($arrUsers);
    }


    public function loadLatestAuthors() : static
    {
        $arrUsers = $this->getRepository()->findLatestAuthors();
        return $this->setEntities($arrUsers);
    }


    public function loadNewOfTheYear() : static
    {
        $arrUsers = $this->getRepository()->findNewOfTheYear();
        return $this->setEntities($arrUsers);
    }


    public function loadTopPosterOfTheYear() : static
    {
        $arrUsers = $this->getRepository()->findTopPostersOfTheYear();
        return $this->setEntities($arrUsers);
    }


    public function getTopPosters(int $topNum = 5) : array
    {
        $arrUsersByPostsNum = [];
        $this->iterate(function(User $user) use (&$arrUsersByPostsNum) {

            $postNum = $user->getCachedData('postNum') ?? $user->getPostNum();

            if( empty($postNum) ) {
                return true;
            }

            $arrUsersByPostsNum[$postNum][] = $user;

            return true;
        });

        ksort($arrUsersByPostsNum, SORT_NUMERIC);

        // get the last 3 values (greatest)
        $arrUsersByPostsNum = array_slice($arrUsersByPostsNum, -$topNum, null, true);

        // greatest first
        $arrUsersByPostsNum = array_reverse($arrUsersByPostsNum, true);

        // flatten the arrays
        $arrTopPosters = array_merge(...$arrUsersByPostsNum);

        return $arrTopPosters;
    }


    public function loadTopAuthorsOfTheYear() : static
    {
        $arrUsers = $this->getRepository()->findTopAuthorsOfTheYear();
        return $this->setEntities($arrUsers);
    }


    public function createService(?UserEntity $entity = null) : User { return $this->factory->createUser($entity); }
}
