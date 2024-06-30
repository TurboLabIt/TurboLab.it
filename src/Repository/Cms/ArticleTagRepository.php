<?php
namespace App\Repository\Cms;

use App\Entity\Cms\ArticleTag;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<ArticleTag>
 *
 * @method ArticleTag|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArticleTag|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArticleTag[]    findAll()
 * @method ArticleTag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleTagRepository extends BaseRepository
{
    const string ENTITY_CLASS_NAME = ArticleTag::class;
}
