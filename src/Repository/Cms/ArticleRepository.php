<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @extends ServiceEntityRepository<Article>
 *
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends BaseCmsRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }


    protected function getQueryBuilder() : QueryBuilder
    {
        return $this->createQueryBuilder('t', 't.id');
    }


    public function findComplete(int $id) : ?Article
    {
        return
            $this->getQueryBuilder()
                // authors
                ->leftJoin('t.authors', 'authorsJunction')
                ->leftJoin('authorsJunction.user', 'user')
                // tags
                ->leftJoin('t.tags', 'tagsJunction')
                ->leftJoin('tagsJunction.tag', 'tag')
                // files
                ->leftJoin('t.files', 'filesJunction')
                ->leftJoin('filesJunction.file', 'file')
                //
                ->addSelect('authorsJunction', 'user', 'tagsJunction', 'tag', 'filesJunction', 'file')
                ->andWhere('t.id = :id')
                    ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
    }


    public function findLatestPublished(?int $num = null) : array
    {
        $qb =
            $this->getQueryBuilder()
                ->andWhere('t.publishingStatus = :published')
                    ->setParameter('published', Article::PUBLISHING_STATUS_PUBLISHED)
                ->orderBy('t.publishedAt', 'DESC');

        if( !empty($num) ) {
            $qb->setMaxResults($num);
        }

        return
            $qb
                ->getQuery()
                ->getResult();
    }
}
