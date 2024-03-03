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
 * @method Topic[]    findAll()
 * @method Topic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TopicRepository extends BaseRepository
{
    /**
     *  4 : area staff
     * 25 : cestinate
     *  7 : area prove
     */
    const array OFFLIMITS_FORUM_IDS = [4,25,7];
    const int COMMENTS_FORUM_ID     = 26;


    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Topic::class);
    }


    public function findLatestForNewsletter() : array
    {
        $arrTopicIds =
            $this->sqlQueryExecute("
                SELECT topic_id FROM turbolab_it_forum." . $this->getTableName() . "
                WHERE FROM_UNIXTIME(topic_last_post_time) BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) AND NOW() AND
                (
                  forum_id != " . static::COMMENTS_FORUM_ID . " OR
                  (forum_id = " . static::COMMENTS_FORUM_ID . " AND topic_posts_approved > 1)
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


    //<editor-fold defaultstate="collapsed" desc="** INTERNAL METHODS **">
    protected function getQueryBuilder() : QueryBuilder
    {
        return
            $this->createQueryBuilder('t', 't.id')
                ->andWhere('t.forumId NOT IN (' . implode(',', self::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.visibility = 1')
                ->andWhere('t.deleteTime = 0')
                ->andWhere('t.status = 0')
                ->orderBy('t.lastPostTime', 'DESC');
    }
    //</editor-fold>
}
