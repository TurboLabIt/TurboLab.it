<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Article;
use App\Entity\Cms\Tag;
use App\Service\Cms\Paginator;
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


    public function findComplete(int $id) : ?Article
    {
        return
            $this->getQueryBuilderComplete()
                ->andWhere('t.id = :id')
                    ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
    }


    public function findMultipleComplete(array $arrIds) : array
    {
        return
            $this->getQueryBuilderComplete()
                ->andWhere('t.id IN(:ids)')
                    ->setParameter('ids', $arrIds)
                ->getQuery()
                ->getResult();
    }


    public function findAllPublished() : array
    {
        return
            $this->getQueryBuilderComplete()
                ->andWhere('t.publishingStatus = :published')
                    ->setParameter('published', Article::PUBLISHING_STATUS_PUBLISHED)
                ->orderBy('t.id', 'ASC')
                ->getQuery()
                ->getResult();
    }


    public function findByTag(Tag $tag, ?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        // we need to extract "having at least this tag" first
        // otherwise, the following call to getQueryBuilderComplete() would load only "this tag" in the articles,
        // excluding other, potentially more important, tag. This would screw Article->getUrl(). Example of the bug:
        // "Come dis/iscriversi dalla newsletter" /newsletter-turbolab.it-1349/something-402
        // when listed in https://turbolab.it/turbolab.it-1
        // had the wrong URL /turbolab.it-1/something-402
        $sqlSelect = "SELECT article_id FROM article_tag WHERE tag_id = :tagId";
        $arrParams = [ "tagId" => $tag->getId() ];
        $query = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect, $arrParams, $page)?->getQuery();

        if( empty($query) ) {
            return null;
        }

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function findLatestPublished(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $sqlSelect = "
          SELECT id FROM article
          WHERE
            publishing_status = " . Article::PUBLISHING_STATUS_PUBLISHED . " AND published_at <= NOW()";

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect, [], $page);

        if( empty($qb) ) {
            return null;
        }

        $query =
            $qb
                ->orderBy('t.publishedAt', 'DESC')
                ->addOrderBy('t.updatedAt', 'DESC')
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function findLatestReadyForReview() : array
    {
        return
            $this->getQueryBuilderComplete()
                ->andWhere('t.publishingStatus = :readyForReview')
                    ->setParameter('readyForReview', Article::PUBLISHING_STATUS_READY_FOR_REVIEW)
                ->andWhere('t.updatedAt >= :dateLimit')
                    ->setParameter('dateLimit', (new \DateTime())->modify('-30 days') )
                ->getQuery()
                ->getResult();
    }


    public function findLatestForNewsletter() : array
    {
        $sqlSelect = "
            SELECT id FROM article
            WHERE
              publishing_status = " . Article::PUBLISHING_STATUS_PUBLISHED . " AND
              published_at BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) AND NOW() AND
              title NOT LIKE 'Questa settimana su TLI%'
            ";

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect);

        if( empty($qb) ) {
            return [];
        }

        return
            $qb
                ->orderBy('t.views', 'DESC')
                ->getQuery()
                ->getResult();
    }


    public function findLatestForSocialSharing(int $maxPublishedMinutes) : array
    {
        $sqlSelect = "
            SELECT id FROM article
            WHERE
              publishing_status = " . Article::PUBLISHING_STATUS_PUBLISHED . " AND
              published_at BETWEEN DATE_SUB(NOW(),INTERVAL :maxPublishedMinutes MINUTE) AND NOW()
            ";

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect, [
            "maxPublishedMinutes" => $maxPublishedMinutes
        ]);

        if( empty($qb) ) {
            return [];
        }

        return
            $qb
                ->orderBy('t.publishedAt', 'ASC')
                ->getQuery()
                ->getResult();
    }


    public function findLatestNewsPublished(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $sqlSelect = "
          SELECT id FROM article
          WHERE
            format = " . Article::FORMAT_NEWS . " AND
            publishing_status = " . Article::PUBLISHING_STATUS_PUBLISHED . " AND published_at <= NOW()";

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect, [], $page);

        if( empty($qb) ) {
            return null;
        }

        $query =
            $qb
                ->orderBy('t.publishedAt', 'DESC')
                ->addOrderBy('t.updatedAt', 'DESC')
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    //<editor-fold defaultstate="collapsed" desc="** INTERNAL METHODS **">
    protected function getQueryBuilder() : QueryBuilder
    {
        return
            $this->createQueryBuilder('t', 't.id')
                ->orderBy('t.updatedAt', 'DESC');
    }


    protected function getQueryBuilderComplete() : QueryBuilder
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
                ->addSelect('authorsJunction', 'user', 'tagsJunction', 'tag', 'filesJunction', 'file');
    }


    protected function getQueryBuilderCompleteFromSqlQuery(string $sqlSelectArtIds, array $arrSqlSelectParams = [], ?int $page = 1) : ?QueryBuilder
    {
        $arrArticleIds = $this->sqlQueryExecute($sqlSelectArtIds, $arrSqlSelectParams)->fetchFirstColumn();
        if( empty($arrArticleIds) ) {
            return null;
        }

        $page = $page ?: 1;
        $startAt = Paginator::ITEMS_PER_PAGE * ($page - 1);

        return
            $this->getQueryBuilderComplete()
                ->andWhere('t.id IN (:articleIds)')
                    ->setParameter("articleIds", $arrArticleIds)
                ->setFirstResult($startAt)
                ->setMaxResults(Paginator::ITEMS_PER_PAGE);
    }
    //</editor-fold>
}
