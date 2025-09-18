<?php
namespace App\Repository;

use App\Entity\Bug;
use App\Service\User;


class BugRepository extends BaseRepository
{
    const string ENTITY_CLASS       = Bug::class;
    const int TIME_LIMIT_MINUTES    = 8;
    const int TIME_LIMIT_BUGS_NUM   = 3;

    public function getRecentByAuthor(User $author, string $authorIpAddress) : array
    {
        return
            $this->getQueryBuilder()
                ->andWhere('t.user = :author OR t.userIpAddress = :authorIpAddress')
                ->andWhere('t.createdAt >= :dateLimit')
                    ->setParameter('author', $author->getEntity())
                    ->setParameter('authorIpAddress', $authorIpAddress)
                    ->setParameter('dateLimit', (new \DateTime())->modify('-' . static::TIME_LIMIT_MINUTES . ' minutes'))
                ->getQuery()->getResult();
    }
}
