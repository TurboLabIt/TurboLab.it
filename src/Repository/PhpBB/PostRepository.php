<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use App\Entity\PhpBB\Post;
use DateTime;
use Doctrine\ORM\QueryBuilder;


class PostRepository extends BasePhpBBRepository
{
    const string ENTITY_CLASS       = Post::class;
    const string DEFAULT_INDEXED_BY = 't.id';


    protected function getQueryBuilder() : QueryBuilder
    {
        return
            parent::getQueryBuilder()
                ->andWhere('t.forumId NOT IN (' . implode(',', Forum::ID_OFFLIMIT) . ')')
                ->andWhere('t.visibility = 1')
                ->andWhere('t.deleteTime = 0')
                ->orderBy('t.postTime', 'DESC');
    }


    public function countNewOfTheYear() : int
    {
        $yearStart  = new DateTime('first day of January this year 00:00:00');
        $yearEnd    = new DateTime('last day of December this year 23:59:59');

        return
            // warning! we're using parent::getQueryBuilder() instead of $this->getQueryBuilder() to skip some conditions
            // this is fine **as long as it's a count**
            parent::getQueryBuilder()
                ->select('COUNT(t)')
                ->andWhere('t.postTime >= :yearStart')
                    ->setParameter('yearStart', $yearStart->getTimestamp())
                ->andWhere('t.postTime <= :yearEnd')
                    ->setParameter('yearEnd', $yearEnd->getTimestamp())
                ->getQuery()->getSingleScalarResult();

    }
}
