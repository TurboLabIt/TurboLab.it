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
        $termToSearch = trim($title);
        if( empty($termToSearch) ) {
            return null;
        }

        $termToSearch = mb_strtolower($termToSearch);

        return
            $this->getQueryBuilder()
                ->andWhere('t.title = :title')
                    ->setParameter('title', $termToSearch)
                ->getQuery()
                ->getOneOrNullResult();
    }


    public function search(null|string|array $title, bool $replaceReplacedWithReplacement) : array
    {
        if( empty($title) ) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilder();
        $whereCondition = '';
        $arrTitles = is_array($title) ? $title : [$title];

        foreach($arrTitles as $key => $termToSearch) {

            $termToSearch = trim($termToSearch);
            if( empty($termToSearch) ) {
                continue;
            }

            if( !empty($whereCondition) ) {
                $whereCondition .= ' OR ';
            }

            $whereCondition .= "t.title LIKE :title$key";
            $queryBuilder->setParameter("title$key", '%' . $this->prepareParamForLikeCondition($termToSearch) . '%');
        }

        if( empty($whereCondition) ) {
            return [];
        }

        $arrTags =
            $queryBuilder
                ->andWhere($whereCondition)
                ->orderBy('t.ranking', 'DESC')
                ->getQuery()->getResult();

        return $replaceReplacedWithReplacement ? $this->replaceReplacedWithReplacement($arrTags) : $arrTags;
    }


    public function findPopular(?int $num = null) : array
    {
        $sqlQuery = "SELECT tag_id FROM article_tag GROUP BY tag_id HAVING COUNT(1) > 1 ORDER BY COUNT(1) DESC ";
        if( !empty($num) ) {
            // cannot be passed as :limit in PDO
            $sqlQuery .= "LIMIT $num";
        }

        $arrIds = $this->getIdsFromSqlQuery($sqlQuery);

        return $this->getById($arrIds);
    }


    protected function replaceReplacedWithReplacement(array $arrTags) : array
    {
        $arrTagsWithoutReplaced = [];

        /** @var Tag $tag */
        foreach($arrTags as $id => $tag) {

            $id = (string)$id;

            if( array_key_exists($id, $arrTagsWithoutReplaced) ) {
                continue;
            }

            $replacement = $tag->getReplacement();
            if( empty($replacement) ) {

                $arrTagsWithoutReplaced[$id] = $tag;
                continue;
            }

            $idReplacement = (string)$replacement->getId();
            if( array_key_exists($idReplacement, $arrTagsWithoutReplaced) ) {
                continue;
            }

            $arrTagsWithoutReplaced[$idReplacement] = $replacement;
        }

        return $arrTagsWithoutReplaced;
    }
}
