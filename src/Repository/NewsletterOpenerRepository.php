<?php
namespace App\Repository;

use App\Entity\NewsletterOpener;
use App\Entity\PhpBB\User;
use Doctrine\DBAL\Result;


class NewsletterOpenerRepository extends BaseRepository
{
    const string ENTITY_CLASS = NewsletterOpener::class;


    public function getByUserOrNew(User $user) : NewsletterOpener
    {
        $opener =
            $this->createQueryBuilder('t')
                ->andWhere('t.user = :user')
                    ->setParameter('user', $user)
                ->getQuery()
                ->getOneOrNullResult();

        if( !empty($opener) ) {
            return $opener;
        }

        return
            (new NewsletterOpener())
                ->setUser($user);
    }


    public function deleteByUserId(int $userId) : Result
    {
        $sqlQuery = "DELETE FROM " . $this->getTableName() . " WHERE user_id = :userId";
        return $this->sqlQueryExecute($sqlQuery, ["userId" => $userId]);
    }
}
