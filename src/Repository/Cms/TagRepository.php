<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @extends ServiceEntityRepository<Tag>
 *
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends BaseCmsRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }


    protected function getQueryBuilder() : QueryBuilder
    {
        return $this->createQueryBuilder('t', 't.id');
    }


    public function findComplete(int $id) : ?Tag
    {
        return
            $this->getQueryBuilder()
                //
                ->leftJoin('t.articles', 'articlesJunction')
                ->leftJoin('articlesJunction.article', 'article')
                // articles authors
                ->leftJoin('article.authors', 'articleAuthorsJunction')
                ->leftJoin('articleAuthorsJunction.user', 'articleUser')
                // articles tags
                ->leftJoin('article.tags', 'articleTagsJunction')
                ->leftJoin('articleTagsJunction.tag', 'articleTag')
                //
                ->addSelect(
                    'articlesJunction', 'article',
                    'articleAuthorsJunction', 'articleUser',
                    'articleTagsJunction', 'articleTag'
                )
                //
                ->andWhere('t.id = :id')
                    ->setParameter('id', $id)
                ->orderBy('article.updatedAt', 'DESC')
                ->getQuery()
                ->getOneOrNullResult();
    }


    public function findLatest(?int $num = null) : array
    {
        $qb =
            $this->getQueryBuilder()
                ->orderBy('t.updatedAt', 'DESC');

        if( !empty($num) ) {
            $qb->setMaxResults($num);
        }

        return
            $qb
                ->getQuery()
                ->getResult();
    }


    public function findByTitle(string $title) : ?Tag
    {
        $qb =
            $this->getQueryBuilder()
                ->andWhere('t.title = :title')
                    ->setParameter('title', $title);

        return
            $qb
                ->getQuery()
                ->getOneOrNullResult();
    }
}
