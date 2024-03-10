<?php
namespace App\Repository;

use App\Entity\NewsletterOpener;
use App\Entity\PhpBB\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Result;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterOpener>
 *
 * @method NewsletterOpener|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewsletterOpener|null findOneBy(array $criteria, array $orderBy = null)
 * @method NewsletterOpener[]    findAll()
 * @method NewsletterOpener[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewsletterOpenerRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterOpener::class);
    }


    public function getByUserOrNew(User $user) : NewsletterOpener
    {
        $opener =
            $this->createQueryBuilder('t')
                ->andWhere('t.user = :user')
                    ->setParameter('user', $user)
                ->getQuery()
                ->getOneOrNullResult();

        if( !empty($opener) ) {
            return $opener;
        }

        return
            (new NewsletterOpener())
                ->setUser($user);
    }


    public function deleteByUserId(int $userId) : Result
    {
        $sqlQuery = "DELETE FROM " . $this->getTableName() . " WHERE user_id = :userId";
        return $this->sqlQueryExecute($sqlQuery, ["userId" => $userId]);
    }
}
