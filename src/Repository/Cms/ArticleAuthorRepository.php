<?php
namespace App\Repository\Cms;

use App\Entity\Cms\ArticleAuthor;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;


/**
 * @extends ServiceEntityRepository<ArticleAuthor>
 *
 * @method ArticleAuthor|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArticleAuthor|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArticleAuthor[]    findAll()
 * @method ArticleAuthor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleAuthorRepository extends BaseRepository
{
    const string ENTITY_CLASS_NAME = ArticleAuthor::class;
}
