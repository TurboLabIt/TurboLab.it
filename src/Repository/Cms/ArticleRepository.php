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
    const string ENTITY_CLASS_NAME = Article::class;

    protected int $itemsPerPage;


    public function __construct(ManagerRegistry $registry, Paginator $paginator)
    {
        parent::__construct($registry);
        $this->itemsPerPage = $paginator->getItemsPerPageNum();
    }


    //<editor-fold defaultstate="collapsed" desc="** QUERY BUILDERS **">
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
                // comments
                ->leftJoin('t.commentsTopic', 'commentsTopic')
                //
                ->addSelect('authorsJunction', 'user', 'tagsJunction', 'tag', 'filesJunction', 'file', 'commentsTopic');
    }
    //</editor-fold>


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
        // excluding other, potentially more important, tags. This would screw Article->getUrl(). Example of the bug:
        // "Come dis/iscriversi dalla newsletter" /newsletter-turbolab.it-1349/something-402
        // when listed in https://turbolab.it/turbolab.it-1
        // had the wrong URL /turbolab.it-1/something-402
        $sqlSelect = "SELECT article_id FROM article_tag WHERE tag_id = :tagId";
        $arrParams = [ "tagId" => $tag->getId() ];

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect, $arrParams);
        if( empty($qb) ) {
            return null;
        }

        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $qb
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function findLatestPublished(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->getQueryBuilderComplete()
                ->andWhere('t.publishingStatus = :published')
                    ->setParameter('published', Article::PUBLISHING_STATUS_PUBLISHED)
                ->andWhere('t.publishedAt <= CURRENT_TIMESTAMP()')
                ->orderBy('t.publishedAt', 'DESC')
                ->addOrderBy('t.updatedAt', 'DESC')
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
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
            $this->getQueryBuilderComplete()
                ->andWhere('t.publishingStatus = :published')
                    ->setParameter('published', Article::PUBLISHING_STATUS_PUBLISHED)
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
            $this->getQueryBuilderComplete()
                ->andWhere('t.format = :formatNews')
                    ->setParameter('formatNews', Article::FORMAT_NEWS)
                ->andWhere('t.publishingStatus = :published')
                    ->setParameter('published', Article::PUBLISHING_STATUS_PUBLISHED)
                ->andWhere('t.publishedAt <= CURRENT_TIMESTAMP()')
                ->orderBy('t.publishedAt', 'DESC')
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function findLatestSecurityNews(int $num = 6) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        // we need to extract "having at least this tag" first
        // otherwise, the following call to getQueryBuilderComplete() would load only "this tag" in the articles,
        // excluding other, potentially more important, tag. This would screw Article->getUrl(). Example of the bug:
        // "Come dis/iscriversi dalla newsletter" /newsletter-turbolab.it-1349/something-402
        // when listed in https://turbolab.it/turbolab.it-1
        // had the wrong URL /turbolab.it-1/something-402
        $sqlSelect = "
            SELECT DISTINCT article_id FROM article_tag WHERE tag_id = :securityTagId AND article_id NOT IN(
              SELECT article_id FROM article_tag WHERE tag_id = :sponsorTagId
            )";

        $arrParams = [
            "securityTagId" => \App\Service\Cms\Tag::ID_SECURITY,
            "sponsorTagId"  => \App\Service\Cms\Tag::ID_SPONSOR
        ];

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect, $arrParams);
        if( empty($qb) ) {
            return null;
        }

        $query =
            $qb
                ->andWhere('t.format = ' . Article::FORMAT_NEWS)
                ->setMaxResults($num)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }


    public function findTopViewsLastYear(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = 20 * ($page - 1);

        $query =
            $this->getQueryBuilderComplete()
                ->where('t.updatedAt >= :oneYearAgo')
                    ->setParameter('oneYearAgo', new \DateTime('-1 year'))
                ->andWhere('t.updatedAt <= :now')
                    ->setParameter('now', new \DateTime())
                ->orderBy('t.views', 'DESC')
                ->setFirstResult($startAt)
                ->setMaxResults(20)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }
}
