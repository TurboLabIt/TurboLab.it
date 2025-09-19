<?php
namespace App\Repository;

use App\Entity\Bug;
use App\Service\User;


class BugRepository extends BaseRepository
{
    const string ENTITY_CLASS       = Bug::class;
    const int TIME_LIMIT_MINUTES    = 10;
    const int TIME_LIMIT_BUGS_NUM   = 3;

    public function getRecentByAuthor(User $author, string $authorIpAddress) : array
    {
        $timeLimit =
            (new \DateTime())
                ->modify('-' . static::TIME_LIMIT_MINUTES . ' minutes');

        return
            $this->getQueryBuilder()
                ->andWhere('t.user = :author OR t.userIpAddress = :authorIpAddress')
                ->andWhere('t.createdAt >= :dateLimit')
                    ->setParameter('author', $author->getEntity())
                    ->setParameter('authorIpAddress', $authorIpAddress)
                    ->setParameter('dateLimit', $timeLimit)
                ->orderBy('t.createdAt', 'ASC')
                ->getQuery()->getResult();
    }
}
