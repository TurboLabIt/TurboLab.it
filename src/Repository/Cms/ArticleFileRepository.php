<?php
namespace App\Repository\Cms;

use App\Entity\Cms\ArticleFile;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleFile>
 *
 * @method ArticleFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArticleFile|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArticleFile[]    findAll()
 * @method ArticleFile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleFileRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleFile::class);
    }
}
