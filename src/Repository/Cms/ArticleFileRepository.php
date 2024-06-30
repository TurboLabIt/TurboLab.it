<?php
namespace App\Repository\Cms;

use App\Entity\Cms\ArticleFile;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

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
    const string ENTITY_CLASS_NAME = ArticleFile::class;
}
