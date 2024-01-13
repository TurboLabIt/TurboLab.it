<?php
namespace App\Repository\Cms;

use App\Entity\Cms\TagAuthor;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TagAuthor>
 *
 * @method TagAuthor|null find($id, $lockMode = null, $lockVersion = null)
 * @method TagAuthor|null findOneBy(array $criteria, array $orderBy = null)
 * @method TagAuthor[]    findAll()
 * @method TagAuthor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagAuthorRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TagAuthor::class);
    }
}
