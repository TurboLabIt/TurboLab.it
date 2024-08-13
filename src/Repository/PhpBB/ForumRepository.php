<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use App\Repository\BaseRepository;
use Doctrine\ORM\QueryBuilder;


class ForumRepository extends BaseRepository
{
    const string ENTITY_CLASS = Forum::class;

    /**
     *  4 : area staff
     * 25 : cestinate
     *  7 : area prove
     */
    const array OFFLIMITS_FORUM_IDS = [4,25,7];
    const int COMMENTS_FORUM_ID     = 26;


    protected function getQueryBuilder() : QueryBuilder
    {
        return
            parent::getQueryBuilder()
                ->andWhere('t.id NOT IN (' . implode(',', ForumRepository::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.parentId NOT IN (' . implode(',', ForumRepository::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.status = 0')
                ->andWhere('t.type = 1')
                ->andWhere('t.last_post_time > 0')
                ->orderBy('t.lastPostTime', 'DESC');
    }
}
