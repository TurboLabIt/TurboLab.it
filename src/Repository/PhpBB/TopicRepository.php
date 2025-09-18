<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use App\Entity\PhpBB\Topic;
use App\Service\Newsletter;
use Doctrine\ORM\QueryBuilder;


class TopicRepository extends BasePhpBBRepository
{
    const string ENTITY_CLASS       = Topic::class;
    const string DEFAULT_INDEXED_BY = 't.id';


    protected function getQueryBuilder() : QueryBuilder
    {
        return
            parent::getQueryBuilder()
                ->andWhere('t.forumId NOT IN (' . implode(',', Forum::ID_OFFLIMIT) . ')')
                ->andWhere('t.visibility = 1')
                ->andWhere('t.deleteTime = 0')
                ->orderBy('t.lastPostTime', 'DESC');
    }


    protected function getSqlSelectQuery() : string
    {
        return "
            SELECT topic_id
            FROM " . $this->getPhpBBTableName() . "
            WHERE
              (
                forum_id != " . Forum::ID_COMMENTS . " OR
                (forum_id = " . Forum::ID_COMMENTS . " AND topic_posts_approved > 1)
              ) AND
            forum_id NOT IN (" . implode(',', Forum::ID_OFFLIMIT) . ")
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
            ");

        if( empty($qb) ) {
            return [];
        }

        return
            $qb
                ->orderBy('t.lastPostTime', 'DESC')
                ->getQuery()->getResult();
    }


    public function findLatestForNewsletter() : array
    {
        $sqlSelect = $this->getSqlSelectQuery() . "
            AND FROM_UNIXTIME(topic_last_post_time) BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) AND NOW()
        ";

        foreach(Newsletter::FORBIDDEN_WORDS as $word) {
            $sqlSelect .= " AND topic_title NOT LIKE '%$word%'";
        }

        $sqlSelect .= "
            ORDER BY topic_views DESC
            LIMIT 25
        ";

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect);

        if( empty($qb) ) {
            return [];
        }

        return
            $qb
                ->orderBy('t.views', 'DESC')
                ->getQuery()->getResult();
    }



    public function getRandomComplete(?int $num = null) : array
    {
        $num = $num ?? 10;

        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                $this->getSqlSelectQuery() . "
                ORDER BY
                    RAND() LIMIT $num
            ");

        if( empty($qb) ) {
            return [];
        }

        return $qb->getQuery()->getResult();
    }
}
