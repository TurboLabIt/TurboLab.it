<?php
namespace App\Repository\Cms;

use App\Entity\Cms\ArticleImage;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleImage>
 *
 * @method ArticleImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArticleImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArticleImage[]    findAll()
 * @method ArticleImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleImageRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleImage::class);
    }
}
