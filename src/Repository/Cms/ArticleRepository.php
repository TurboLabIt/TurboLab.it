<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Article;
use App\Entity\Cms\Tag;
use App\Service\Cms\Paginator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
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


    public function findComplete(int $id) : ?Article
    {
        return
            $this->getQueryBuilderComplete()
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


    public function findLatestReadyForReview() : array
    {
        return
            $this->getQueryBuilder()
                ->andWhere('t.publishingStatus = :readyForReview')
                    ->setParameter('readyForReview', Article::PUBLISHING_STATUS_READY_FOR_REVIEW)
                ->andWhere('t.updatedAt >= :dateLimit')
                    ->setParameter('dateLimit', (new \DateTime())->modify('-30 days') )
                ->orderBy('t.updatedAt', 'ASC')
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
        $db  = $this->getEntityManager()->getConnection();
        $sql = "SELECT article_id FROM article_tag WHERE tag_id = :tagId";
        $stmt = $db->prepare($sql);
        $stmt->bindValue('tagId', $tag->getId(), ParameterType::INTEGER);
        $arrArticleIds = $stmt->executeQuery()->fetchFirstColumn();

        if( empty($arrArticleIds) ) {
            return null;
        }

        $page       = $page ?: 1;
        $startAt    = Paginator::ITEMS_PER_PAGE * ($page - 1);

        $query =
            $this->getQueryBuilderComplete()
                ->andWhere('t.id IN (:articleIds)')
                    ->setParameter("articleIds", $arrArticleIds)
                ->setFirstResult($startAt)
                ->setMaxResults(Paginator::ITEMS_PER_PAGE)
                ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator;
    }
}
