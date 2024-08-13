<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Tag;
use App\Repository\BaseRepository;
use Doctrine\ORM\QueryBuilder;


class TagRepository extends BaseRepository
{
    const string ENTITY_CLASS               = Tag::class;
    const string DEFAULT_ORDER_BY           = 't.title';
    const string DEFAULT_ORDER_DIRECTION    = 'ASC';


    protected function getQueryBuilderComplete() : QueryBuilder
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
                );
    }


    public function findLatest(?int $num = null) : array
    {
        $qb = $this->getQueryBuilder()->orderBy('t.updatedAt', 'DESC');

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
        return
            $this->getQueryBuilder()
                ->andWhere('t.title = :title')
                    ->setParameter('title', $title)
                ->getQuery()
                ->getOneOrNullResult();
    }
}
