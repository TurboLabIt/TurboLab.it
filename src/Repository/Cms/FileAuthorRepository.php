<?php
namespace App\Repository\Cms;

use App\Entity\Cms\FileAuthor;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FileAuthor>
 *
 * @method FileAuthor|null find($id, $lockMode = null, $lockVersion = null)
 * @method FileAuthor|null findOneBy(array $criteria, array $orderBy = null)
 * @method FileAuthor[]    findAll()
 * @method FileAuthor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FileAuthorRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FileAuthor::class);
    }
}
