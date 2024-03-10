<?php
namespace App\Repository;

use App\Entity\NewsletterExpiringWarn;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Result;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @extends ServiceEntityRepository<NewsletterExpiringWarn>
 *
 * @method NewsletterExpiringWarn|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewsletterExpiringWarn|null findOneBy(array $criteria, array $orderBy = null)
 * @method NewsletterExpiringWarn[]    findAll()
 * @method NewsletterExpiringWarn[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewsletterExpiringWarnRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterExpiringWarn::class);
    }


    public function deleteByUserId(int $userId) : Result
    {
        $sqlQuery = "DELETE FROM " . $this->getTableName() . " WHERE user_id = :userId";
        return $this->sqlQueryExecute($sqlQuery, ["userId" => $userId]);
    }
}
