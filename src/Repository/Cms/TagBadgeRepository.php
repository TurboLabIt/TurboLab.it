<?php

namespace App\Repository\Cms;

use App\Entity\Cms\TagBadge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TagBadge>
 *
 * @method TagBadge|null find($id, $lockMode = null, $lockVersion = null)
 * @method TagBadge|null findOneBy(array $criteria, array $orderBy = null)
 * @method TagBadge[]    findAll()
 * @method TagBadge[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagBadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TagBadge::class);
    }

//    /**
//     * @return TagBadge[] Returns an array of TagBadge objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TagBadge
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
