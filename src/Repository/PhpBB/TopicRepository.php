<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Topic;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @extends ServiceEntityRepository<Topic>
 *
 * @method Topic|null find($id, $lockMode = null, $lockVersion = null)
 * @method Topic|null findOneBy(array $criteria, array $orderBy = null)
 * @method Topic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TopicRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Topic::class);
    }


    public function findLatest(int $num = 10) : array
    {
        $arrTopicIds =
            $this->sqlQueryExecute("
                SELECT topic_id FROM turbolab_it_forum." . $this->getTableName() . "
                WHERE
                (
                  forum_id != " . ForumRepository::COMMENTS_FORUM_ID . " OR
                  (forum_id = " . ForumRepository::COMMENTS_FORUM_ID . " AND topic_posts_approved > 1)
                )
                ORDER BY topic_last_post_time DESC
                LIMIT " . $num
            )->fetchFirstColumn();

        if( empty($arrTopicIds) ) {
            return [];
        }

        return
            $this->getQueryBuilder()
                ->andWhere('t.id IN (:arrTopicIds)')
                    ->setParameter("arrTopicIds", $arrTopicIds)
                ->orderBy('t.lastPostTime', 'DESC')
                ->getQuery()
                ->getResult();
    }


    public function findLatestForNewsletter() : array
    {
        $arrTopicIds =
            $this->sqlQueryExecute("
                SELECT topic_id FROM turbolab_it_forum." . $this->getTableName() . "
                WHERE FROM_UNIXTIME(topic_last_post_time) BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) AND NOW() AND
                (
                  forum_id != " . ForumRepository::COMMENTS_FORUM_ID . " OR
                  (forum_id = " . ForumRepository::COMMENTS_FORUM_ID . " AND topic_posts_approved > 1)
                )
            ")->fetchFirstColumn();

        if( empty($arrTopicIds) ) {
            return [];
        }

        return
            $this->getQueryBuilder()
                ->andWhere('t.id IN (:arrTopicIds)')
                    ->setParameter("arrTopicIds", $arrTopicIds)
                ->orderBy('t.views', 'DESC')
                ->getQuery()
                ->getResult();
    }


    public function findAll() : array
    {
        return
            $this->getQueryBuilder()
                ->orderBy('t.id', 'ASC')
                ->getQuery()
                ->getResult();
    }


    //<editor-fold defaultstate="collapsed" desc="** INTERNAL METHODS **">
    protected function getQueryBuilder() : QueryBuilder
    {
        return
            $this->createQueryBuilder('t', 't.id')
                ->andWhere('t.forumId NOT IN (' . implode(',', ForumRepository::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.visibility = 1')
                ->andWhere('t.deleteTime = 0')
                ->andWhere('t.status = 0')
                ->orderBy('t.lastPostTime', 'DESC');
    }
    //</editor-fold>
}
