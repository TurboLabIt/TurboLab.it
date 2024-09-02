<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use Doctrine\ORM\QueryBuilder;


class ForumRepository extends BasePhpBBRepository
{
    const string ENTITY_CLASS = Forum::class;


    protected function getQueryBuilder() : QueryBuilder
    {
        return
            parent::getQueryBuilder()
                ->andWhere('t.id NOT IN (' . implode(',', Forum::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.parentId NOT IN (' . implode(',', Forum::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.status = 0')
                ->andWhere('t.type = 1')
                ->andWhere('t.last_post_time > 0')
                ->orderBy('t.last_post_time', 'DESC');
    }
}
