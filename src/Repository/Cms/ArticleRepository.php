<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Article;
use App\Entity\Cms\Tag;
use App\Entity\PhpBB\User;
use App\Repository\BaseRepository;
use App\Service\Cms\Paginator;
use App\Service\Newsletter;
use DateTime;
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
                //spotlight
                ->leftJoin('t.spotlight', 'spotlight')
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
                ->addSelect('spotlight', 'authorsJunction', 'user', 'tagsJunction', 'tag',/* 'filesJunction', 'file',*/ 'commentsTopic');
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

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findLatestUpdated(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        // articles by `system`
        $arrArticleIdBySystem =
            $this->sqlQueryExecute(
            "SELECT DISTINCT article_id FROM article_author WHERE user_id = :authorId", [
                'authorId' => \App\Service\User::ID_SYSTEM
            ])->fetchFirstColumn();

        $query =
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUSES_LISTABLE, false)
                ->andWhere('t.id NOT IN(:articleIdBySystem)')
                    ->setParameter('articleIdBySystem', $arrArticleIdBySystem)
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->orderBy('t.updatedAt', 'DESC')
                ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
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

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findByAuthor(User $author, ?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        // we need to extract "having at least this author" first
        // otherwise, the call to getQueryBuilderComplete() would load ONLY "this author" in the articles,
        // excluding other authors.
        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                "SELECT DISTINCT article_id FROM article_author WHERE user_id = :authorId",
                [ "authorId" => $author->getId() ]
            );

        if( empty($qb) ) {
            return null;
        }

        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->addWherePublishingStatus($qb, Article::PUBLISHING_STATUS_PUBLISHED, false)
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findDrafts(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_DRAFT, false)
                ->orderBy('t.updatedAt', 'DESC')
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findDraftsByAuthor(User $author) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        // we need to extract "having at least this author" first
        // otherwise, the call to getQueryBuilderComplete() would load ONLY "this author" in the articles,
        // excluding other authors.
        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                "SELECT DISTINCT article_id FROM article_author WHERE user_id = :authorId",
                [ "authorId" => $author->getId() ]
            );

        if( empty($qb) ) {
            return null;
        }

        $query =
            $this->addWherePublishingStatus($qb, Article::PUBLISHING_STATUS_DRAFT, false)
                ->orderBy('t.views', 'DESC')
                ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findInReviewByAuthor(User $author) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        // we need to extract "having at least this author" first
        // otherwise, the call to getQueryBuilderComplete() would load ONLY "this author" in the articles,
        // excluding other authors.
        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                "SELECT DISTINCT article_id FROM article_author WHERE user_id = :authorId",
                [ "authorId" => $author->getId() ]
            );

        if( empty($qb) ) {
            return null;
        }

        $query =
            $this->addWherePublishingStatus($qb, Article::PUBLISHING_STATUS_READY_FOR_REVIEW, false)
                ->orderBy('t.views', 'DESC')
                ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findLatestPublishedByAuthor(User $author) : ?array
    {
        $arrLatestArticles = [];

        // we need to extract "having at least this author" first
        // otherwise, the call to getQueryBuilderComplete() would load ONLY "this author" in the articles,
        // excluding other authors.
        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                "SELECT DISTINCT article_id FROM article_author WHERE user_id = :authorId",
                [ "authorId" => $author->getId() ]
            );

        if( empty($qb) ) {
            return null;
        }

        // upcoming (all)
        $upcomingArticles =
            $this->addWherePublishingStatus(clone $qb, Article::PUBLISHING_STATUS_PUBLISHED, false)
                ->andWhere('t.publishedAt IS NOT NULL AND t.publishedAt > CURRENT_TIMESTAMP()')
                ->orderBy('t.publishedAt', 'DESC')
                ->getQuery()->getResult();

        foreach($upcomingArticles as $article) {

            $articleId = (string)$article->getId();
            $arrLatestArticles[$articleId] = $article;
        }


        // published in the past (latest)
        $pastPublishedArticles =
            $this->addWherePublishingStatus($qb)
                ->orderBy('t.publishedAt', 'DESC')
                ->getQuery()->getResult();

        $maxPublishedToAdd = 10;
        foreach($pastPublishedArticles as $article) {

            $articleId = (string)$article->getId();

            if( array_key_exists($articleId, $arrLatestArticles) ) {
                continue;
            }

            $arrLatestArticles[$articleId] = $article;

            $maxPublishedToAdd--;

            if( $maxPublishedToAdd == 0 ) {
                break;
            }
        }

        return $arrLatestArticles;
    }


    public function findKoByAuthor(User $author) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        // we need to extract "having at least this author" first
        // otherwise, the call to getQueryBuilderComplete() would load ONLY "this author" in the articles,
        // excluding other authors.
        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                "SELECT DISTINCT article_id FROM article_author WHERE user_id = :authorId",
                [ "authorId" => $author->getId() ]
            );

        if( empty($qb) ) {
            return null;
        }

        $query =
            $this->addWherePublishingStatus($qb, Article::PUBLISHING_STATUS_KO, false)
                ->orderBy('t.updatedAt', 'DESC')
                ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findLatestReadyForReview(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_READY_FOR_REVIEW, false)
                //->andWhere('t.updatedAt >= :dateLimit')
                    //->setParameter('dateLimit', (new DateTime())->modify('-45 days') )
                ->orderBy('t.updatedAt', 'ASC')
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findLatestForNewsletter() : array
    {
        $sqlSelect = "
            SELECT id FROM " . $this->getTableName() . "
            WHERE
              published_at BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) AND NOW() AND
              title NOT LIKE 'Questa settimana su TLI%'
            ";

        foreach(Newsletter::FORBIDDEN_WORDS as $word) {
            $sqlSelect .= " AND title NOT LIKE '%$word%' AND abstract NOT LIKE '%$word%'";
        }

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
        $lowLimit   = (new DateTime())->modify('-' . $maxPublishedMinutes . " minutes");
        // reset the time to zero seconds
        $lowHour    = (int)$lowLimit->format('G');
        $lowMinute  = (int)$lowLimit->format('i');
        $lowLimit->setTime($lowHour, $lowMinute);

        $highLimit  = (new DateTime());
        // reset the time to zero seconds
        $highHour   = (int)$highLimit->format('G');
        $highMinute = (int)$highLimit->format('i');
        $highLimit->setTime($highHour, $highMinute);

        return
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED, false)
                //  # must be: GreaterOrEqualThan and LessThan - see https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/social-network-sharing.md
                ->andWhere('t.publishedAt >= :lowLimit')
                    ->setParameter('lowLimit', $lowLimit)
                ->andWhere('t.publishedAt < :highLimit')
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

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
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

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findTopViews(?int $page = 1, ?int $maxDaysAgo = null) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED, false)
                ->orderBy('t.views', 'DESC');

        if( !empty($maxDaysAgo) ) {

            $query
                ->andWhere('t.updatedAt >= :daysAgo')
                    ->setParameter('daysAgo', new DateTime("-$maxDaysAgo days"));
        }

        $query
                ->setFirstResult($startAt)
                ->setMaxResults($this->itemsPerPage)
                ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findTopComments(?int $page = 1, ?int $maxDaysAgo = null) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED, false)
                ->andWhere('commentsTopic.postNum > 1')
                ->orderBy('commentsTopic.postNum', 'DESC');

        if( !empty($maxDaysAgo) ) {

            $query
                ->andWhere('t.updatedAt >= :daysAgo')
                ->setParameter('daysAgo', new DateTime("-$maxDaysAgo days"));
        }

        $query
            ->setFirstResult($startAt)
            ->setMaxResults($this->itemsPerPage)
            ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
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

        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery("
                ( " . str_ireplace(["##OP##", "##DIR##"], ["<", "DESC"], $sqlSelect) . " )
                UNION
                ( " . str_ireplace(["##OP##", "##DIR##"], [">", "ASC"], $sqlSelect) . " )
            ", [
                "publishingStatus"  => Article::PUBLISHING_STATUS_PUBLISHED,
                "articleDate"       => $article->getPublishedAt()->format('Y-m-d H:i:s'),
                "articleId"         => $article->getId(),
            ]);


        if( empty($qb) ) {
            return [];
        }

        $arrResults = $qb->getQuery()->getResult();

        uasort($arrResults, function(Article $a1, Article $a2) {
            return $a1->getPublishedAt() <=> $a2->getPublishedAt();
        });

        return $arrResults;
    }


    public function getRandomComplete(?int $num = null) : array
    {
        $num = $num ?? $this->itemsPerPage;

        if( $num == 1 ) {

            $sqlQuery = "
                SELECT id FROM ". $this->getTableName() . "
                WHERE
                    publishing_status = " . Article::PUBLISHING_STATUS_PUBLISHED . "
                ORDER BY
                    RAND() LIMIT 1
            ";

            $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlQuery);

            if( empty($qb) ) {
                return [];
            }

            return $qb->getQuery()->getResult();
        }

        $sqlQueryTemplate = "
            (SELECT id FROM ". $this->getTableName() . " WHERE
                publishing_status = " . Article::PUBLISHING_STATUS_PUBLISHED . " AND
                format = ##FORMAT## ORDER BY RAND() LIMIT ##NUM##)
        ";

        $arrParams = [[
            "##FORMAT##"=> Article::FORMAT_ARTICLE,
            "##NUM##"   => (int)ceil( $num / 2 )
        ],[
            "##FORMAT##"=> Article::FORMAT_NEWS,
            "##NUM##"   => (int)floor( $num / 2 )
        ]];

        $sqlQuery = '';
        for($i=0; $i < 2; $i++) {

            $arrQueryParams = $arrParams[$i];
            $sqlQuery .= str_replace( array_keys($arrQueryParams), $arrQueryParams, $sqlQueryTemplate);

            if( $i == 0 ) {
                $sqlQuery .= 'UNION';
            }
        }

        $sqlQuery .= 'ORDER BY RAND()';

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlQuery);

        if( empty($qb) ) {
            return [];
        }

        return $qb->getQuery()->getResult();
    }


    public function getFirstAndLastPublished() : array
    {
        $sqlQueryTemplate = "
            (SELECT id FROM ". $this->getTableName() . " WHERE
                publishing_status = " . Article::PUBLISHING_STATUS_PUBLISHED . " AND
                published_at IS NOT NULL
                ORDER BY published_at ##DIRECTION## LIMIT 1)
        ";

        $arrParams = [[
            "##DIRECTION##" => 'ASC'
        ],[
            "##DIRECTION##" => 'DESC'
        ]];

        $sqlQuery = '';
        for($i=0; $i < 2; $i++) {

            $arrQueryParams = $arrParams[$i];
            $sqlQuery .= str_replace( array_keys($arrQueryParams), $arrQueryParams, $sqlQueryTemplate);

            if( $i == 0 ) {
                $sqlQuery .= 'UNION';
            }
        }

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlQuery);

        if( empty($qb) ) {
            return [];
        }

        $arrEntities = $qb->getQuery()->getResult();
        uasort($arrEntities, function(Article $a, Article $b) {
            return $a->getPublishedAt() <=> $b->getPublishedAt();
        });

        return $arrEntities;
    }


    public function getByPublishedDateInterval(DateTime $startDate, DateTime $endDate) : array
    {
        return
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED, false)
                ->andWhere('t.publishedAt >= :lowLimit')
                    ->setParameter('lowLimit', $startDate)
                ->andWhere('t.publishedAt < :highLimit')
                    ->setParameter('highLimit', $endDate)
                ->orderBy('t.publishedAt', 'ASC')
                ->getQuery()
                ->getResult();
    }


    public function getSerp(string $termToSearch) : array
    {
        $termToSearch   = preg_replace('/[^a-z0-9_ ]/i', '', $termToSearch);
        $arrTerms       = explode(' ', $termToSearch);

        foreach($arrTerms as $k => $term) {

            $term = trim($term);

            if( empty($term) || mb_strlen($term) < 2 ) {

                unset($arrTerms[$k]);
                continue;
            }

            $arrTerms[$k] = "+$term";
        }

        $termToSearchPrepared = implode(' ', $arrTerms);

        $sqlSelect = "
            SELECT id, MATCH(title) AGAINST(:termToSearch IN BOOLEAN MODE) AS ranking FROM " . $this->getTableName() . "
            WHERE
                MATCH(title) AGAINST(:termToSearch IN BOOLEAN MODE) AND
                publishing_status = " . Article::PUBLISHING_STATUS_PUBLISHED . "
            LIMIT 50
        ";

        $arrIds = $this->sqlQueryExecute($sqlSelect, ['termToSearch' => $termToSearchPrepared])->fetchFirstColumn();
        if( empty($arrIds) ) {
            return [];
        }

        $arrArticles =
            $this->getQueryBuilderComplete()
                ->andWhere('t.id IN (:ids)')
                ->setParameter("ids", $arrIds)
                ->getQuery()
                ->getResult();

        $arrReorder = array_flip($arrIds);
        foreach($arrReorder as $articleId => $value) {

            if( !array_key_exists($articleId, $arrArticles) ) {

                unset($arrReorder[$articleId]);
                continue;
            }

            $arrReorder[$articleId] = $arrArticles[$articleId];
        }

        return $arrReorder;
    }


    public function findPastYearsTitled(?int $page = 1) : ?\Doctrine\ORM\Tools\Pagination\Paginator
    {
        $page    = $page ?: 1;
        $startAt = $this->itemsPerPage * ($page - 1);

        $query =
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED, false);

        $orX = $query->expr()->orX();

        foreach (['198', '199', '200', '201', '202', '203'] as $yearPrefix) {

            $paramName = "year_prefix_$yearPrefix";
            $query->setParameter($paramName, "%$yearPrefix%");
            $orX->add($query->expr()->like('t.title', ':' . $paramName));
        }

        $currentYear = date('Y');

        $query
            ->andWhere($orX)
            ->andWhere($query->expr()->notLike('t.title', $query->expr()->literal("%$currentYear%")))
            ->andWhere('t.format = ' . Article::FORMAT_ARTICLE)
            ->andWhere('t.archived = false')
            ->orderBy('t.views', 'DESC');

        $query
            ->setFirstResult($startAt)
            ->setMaxResults($this->itemsPerPage)
            ->getQuery();

        return new \Doctrine\ORM\Tools\Pagination\Paginator($query);
    }


    public function findForScheduling() : array
    {
        return
            $this->getQueryBuilderCompleteWherePublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED, false)
                ->andWhere('t.format = ' . Article::FORMAT_ARTICLE)
                ->andWhere('t.publishedAt >= :lowLimit')
                    ->setParameter('lowLimit', (new DateTime())->modify('today midnight') )
                ->orderBy('t.publishedAt', 'ASC')
                ->getQuery()
                ->getResult();
    }
}
