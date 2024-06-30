<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;


/**
 * @extends ServiceEntityRepository<Forum>
 *
 * @method Forum|null find($id, $lockMode = null, $lockVersion = null)
 * @method Forum|null findOneBy(array $criteria, array $orderBy = null)
 * @method Forum[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ForumRepository extends BaseRepository
{
    const string ENTITY_CLASS_NAME = Forum::class;

    /**
     *  4 : area staff
     * 25 : cestinate
     *  7 : area prove
     */
    const array OFFLIMITS_FORUM_IDS = [4,25,7];
    const int COMMENTS_FORUM_ID     = 26;


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
                ->andWhere('t.id NOT IN (' . implode(',', ForumRepository::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.parentId NOT IN (' . implode(',', ForumRepository::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.status = 0')
                ->andWhere('t.type = 1')
                ->andWhere('t.last_post_time > 0')
                ->orderBy('t.lastPostTime', 'DESC');
    }
    //</editor-fold>
}
