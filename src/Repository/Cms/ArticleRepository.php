<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Article;
use App\Entity\Cms\Tag;
use App\Repository\BaseRepository;
use App\Service\Cms\Paginator;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;


class ArticleRepository extends BaseRepository
{
    const string ENTITY_CLASS       = Article::class;
    const string DEFAULT_ORDER_BY   = 't.publishedAt';

    //<editor-fold defaultstate="collapsed" desc="*** 🍹 Class properties ***">
    protected int $itemsPerPage;
    //</editor-fold>


    public function __construct(ManagerRegistry $registry, Paginator $paginator)
    {
        parent::__construct($registry);
        $this->itemsPerPage = $paginator->getItemsPerPageNum();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 👷 Query Builders ***">
    protected function getQueryBuilderComplete() : QueryBuilder
    {
        return
            parent::getQueryBuilderComplete()
                // authors
                ->leftJoin('t.authors', 'authorsJunction')
                ->leftJoin('authorsJunction.user', 'user')
                // tags
                ->leftJoin('t.tags', 'tagsJunction')
                ->leftJoin('tagsJunction.tag', 'tag')
                // files
                //->leftJoin('t.files', 'filesJunction')
                //->leftJoin('filesJunction.file', 'file')
                // comments
                ->leftJoin('t.commentsTopic', 'commentsTopic')
                //
                ->addSelect('authorsJunction', 'user', 'tagsJunction', 'tag',/* 'filesJunction', 'file',*/ 'commentsTopic');
    }


    protected function getQueryBuilderCompleteWherePublishingStatus(
        array|int $publishingStatus = Article::PUBLISHING_STATUS_PUBLISHED, bool $excludeUpcoming = true
    ) : QueryBuilder
    {
        return $this->addWherePublishingStatus($this->getQueryBuilderComplete(), $publishingStatus, $excludeUpcoming);
    }


    protected function addWherePublishingStatus(
        QueryBuilder $queryBuilder, array|int $publishingStatus = Article::PUBLISHING_STATUS_PUBLISHED,
        bool $excludeUpcoming = true
    ) : QueryBuilder
    {
        if( is_array($publishingStatus) ) {

            $queryBuilder->andWhere('t.publishingStatus IN(:publishingStatus)');

        } else {

            $queryBuilder->andWhere('t.publishingStatus = :publishingStatus');
        }

        $queryBuilder->setParameter('publishingStatus', $publishingStatus);

        if($excludeUpcoming) {
            $queryBuilder->andWhere('t.publishedAt <= CURRENT_TIMESTAMP()');
        }

        return $queryBuilder;
    }
    //</editor-fold>


    public function findLatestPublished(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->getQueryBuilderCompleteWherePublishingStatus()
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function findAllPublished() : array
    {
        return
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED, false)
                ->getQuery()
                ->getResult();
    }


    public function findByTag(Tag $tag, ?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        // we need to extract "having at least this tag" first
        // otherwise, the call to getQueryBuilderComplete() would load ONLY "this tag" in the articles,
        // excluding other tags. This would also screw Article->getUrl(). Example of the bug:
        // "Come dis/iscriversi dalla newsletter" /newsletter-turbolab.it-1349/something-402
        // when listed in https://turbolab.it/turbolab.it-1
        // had the wrong URL /turbolab.it-1/something-402
        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                "SELECT DISTINCT article_id FROM article_tag WHERE tag_id = :tagId",
                [ "tagId" => $tag->getId() ]
            );

        if( empty($qb) ) {
            return null;
        }

        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->addWherePublishingStatus($qb)
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function findLatestReadyForReview() : array
    {
        return
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_READY_FOR_REVIEW, false)
                ->andWhere('t.updatedAt >= :dateLimit')
                    ->setParameter('dateLimit', (new \DateTime())->modify('-45 days') )
                ->getQuery()
                ->getResult();
    }


    public function findLatestForNewsletter() : array
    {
        $sqlSelect = "
            SELECT id FROM article
            WHERE
              published_at BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) AND NOW() AND
              title NOT LIKE 'Questa settimana su TLI%'
            ";

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect);

        if( empty($qb) ) {
            return [];
        }

        return
            $this->addWherePublishingStatus($qb)
                ->orderBy('t.views', 'DESC')
                ->getQuery()
                ->getResult();
    }


    public function findLatestForSocialSharing(int $maxPublishedMinutes) : array
    {
        $lowLimit   = (new \DateTime())->modify('-' . $maxPublishedMinutes . " minutes");
        // reset the time to zero seconds
        $lowHour    = (int)$lowLimit->format('G');
        $lowMinute  = (int)$lowLimit->format('i');
        $lowLimit->setTime($lowHour, $lowMinute, 0);

        $highLimit  = (new \DateTime());
        // reset the time to zero seconds
        $highHour   = (int)$highLimit->format('G');
        $highMinute = (int)$highLimit->format('i');
        $highLimit->setTime($highHour, $highMinute, 0);

        return
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED, false)
                //  # must be: GreaterOrEqualThan and LessThan - see https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/social-network-sharing.md
                ->andWhere('t.published_at >= :lowLimit')
                    ->setParameter('lowLimit', $lowLimit)
                ->andWhere('t.published_at < :highLimit')
                    ->setParameter('highLimit', $highLimit)
                ->orderBy('t.publishedAt', 'ASC')
                ->getQuery()
                ->getResult();
    }


    public function findLatestNewsPublished(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->getQueryBuilderCompleteWherePublishingStatus()
                ->andWhere('t.format = :formatNews')
                    ->setParameter('formatNews', Article::FORMAT_NEWS)
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function findLatestSecurityNews(?int $num = null) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $num = $num ?? $this->itemsPerPage;

        // we need to extract "having at least this tag" first
        // otherwise, the call to getQueryBuilderComplete() would load ONLY "this tag" in the articles,
        // excluding other tags. This would also screw Article->getUrl()
        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery("
            SELECT DISTINCT article_id FROM article_tag WHERE tag_id = :securityTagId AND article_id NOT IN(
              SELECT article_id FROM article_tag WHERE tag_id = :sponsorTagId
            )", [
                "securityTagId" => \App\Service\Cms\Tag::ID_SECURITY,
                "sponsorTagId"  => \App\Service\Cms\Tag::ID_SPONSOR
            ]);

        if( empty($qb) ) {
            return null;
        }

        $query =
            $this->addWherePublishingStatus($qb)
                ->andWhere('t.format = ' . Article::FORMAT_NEWS)
                ->setMaxResults($num)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function findTopViewsLastYear(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page       = $page ?: 1;
        $numItems   = $this->itemsPerPage % 2 == 0 ? $this->itemsPerPage : ( $this->itemsPerPage - 1 );
        $startAt    = $numItems * ($page - 1);

        $query =
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED, false)
                ->andWhere('t.updatedAt >= :oneYearAgo')
                    ->setParameter('oneYearAgo', new \DateTime('-1 year'))
                ->andWhere('t.updatedAt <= :now')
                    ->setParameter('now', new \DateTime())
                ->orderBy('t.views', 'DESC')
                ->setFirstResult($startAt)
                ->setMaxResults($numItems)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function getPrevNextArticle(Article $article) : array
    {
        $sqlSelect = "
            SELECT id FROM " . $this->getTableName() . "
            WHERE
                publishing_status = :publishingStatus AND published_at IS NOT NULL AND
                published_at ##OP## :articleDate AND id != :articleId
                ORDER BY published_at ##DIR##
                LIMIT 1
            ";

        $arrResults =
            $this->getQueryBuilderCompleteFromSqlQuery("
                ( " . str_ireplace(["##OP##", "##DIR##"], ["<", "DESC"], $sqlSelect) . " )
                UNION
                ( " . str_ireplace(["##OP##", "##DIR##"], [">", "ASC"], $sqlSelect) . " )
            ", [
                "publishingStatus"  => Article::PUBLISHING_STATUS_PUBLISHED,
                "articleDate"       => $article->getPublishedAt()->format('Y-m-d H:i:s'),
                "articleId"         => $article->getId(),
            ])
            ->getQuery()->getResult();

        if( empty($arrResults) ) {
            return [];
        }

        uasort($arrResults, function(Article $a1, Article $a2) {
            return $a1->getPublishedAt() <=> $a2->getPublishedAt();
        });

        return $arrResults;
    }
}
