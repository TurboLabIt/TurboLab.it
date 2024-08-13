<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Topic;
use App\Repository\BaseRepository;
use Doctrine\ORM\QueryBuilder;


class TopicRepository extends BaseRepository
{
    const string ENTITY_CLASS       = Topic::class;
    const string DEFAULT_INDEXED_BY = 't.id';


    protected function getQueryBuilder() : QueryBuilder
    {
        return
            parent::getQueryBuilder()
                ->andWhere('t.forumId NOT IN (' . implode(',', ForumRepository::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.visibility = 1')
                ->andWhere('t.deleteTime = 0')
                ->andWhere('t.status = 0')
                ->orderBy('t.lastPostTime', 'DESC');
    }


    protected function getSqlSelectQuery() : string
    {
        return "
            SELECT topic_id FROM turbolab_it_forum." . $this->getTableName() . "
            WHERE
                (
                  forum_id != " . ForumRepository::COMMENTS_FORUM_ID . " OR
                  (forum_id = " . ForumRepository::COMMENTS_FORUM_ID . " AND topic_posts_approved > 1)
                ) AND
                forum_id NOT IN (" . implode(',', ForumRepository::OFFLIMITS_FORUM_IDS) . ")
        ";
    }


    public function findLatest(?int $num = null) : array
    {
        $num = $num ?? 10;

        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                $this->getSqlSelectQuery() . "
                ORDER BY topic_last_post_time DESC
                LIMIT $num
            "
        );

        if( empty($qb) ) {
            return [];
        }

        return
            $qb
                ->orderBy('t.lastPostTime', 'DESC')
                ->getQuery()
                ->getResult();
    }


    public function findLatestForNewsletter() : array
    {
        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                $this->getSqlSelectQuery() . "
                AND FROM_UNIXTIME(topic_last_post_time) BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) AND NOW()
                ORDER BY topic_views DESC
                LIMIT 25
            "
            );

        if( empty($qb) ) {
            return [];
        }

        return
            $qb
                ->orderBy('t.views', 'DESC')
                ->getQuery()
                ->getResult();
    }
}
