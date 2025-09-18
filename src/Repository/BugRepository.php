<?php
namespace App\Repository;

use App\Entity\Bug;
use App\Service\User;


class BugRepository extends BaseRepository
{
    const string ENTITY_CLASS   = Bug::class;
    const int TIME_LIMIT_HOURS  = 8;
    const int TIME_LIMIT_BUGS   = 5;

    public function getRecentByAuthor(User $author, string $authorIpAddress) : array
    {
        return
            $this->getQueryBuilder()
                ->andWhere('t.user = :author OR t.userIpAddress = :authorIpAddress')
                ->andWhere('t.createdAt >= :dateLimit')
                    ->setParameter('author', $author->getEntity())
                    ->setParameter('authorIpAddress', $authorIpAddress)
                    ->setParameter('dateLimit', (new \DateTime())->modify('-' . static::TIME_LIMIT_HOURS . ' hour'))
                ->getQuery()->getResult();
    }
}
