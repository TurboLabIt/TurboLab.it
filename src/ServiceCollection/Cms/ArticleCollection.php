<?php
namespace App\ServiceCollection\Cms;

use App\Entity\BaseEntity;
use App\Factory\Cms\ArticleFactory;
use App\Service\Cms\Article as ArticleService;
use App\Entity\Cms\Article as ArticleEntity;
use Doctrine\ORM\EntityManagerInterface;


class ArticleCollection extends BaseCmsServiceCollection
{
    const ENTITY_CLASS          = ArticleEntity::class;
    const NOT_FOUND_EXCEPTION   = 'App\Exception\ArticleNotFoundException';


    public function __construct(protected EntityManagerInterface $em, protected ArticleFactory $factory)
    { }


    public function loadLatestPublished(int $num) : static
    {
        $arrArticles = $this->em->getRepository(ArticleEntity::class)->findLatestPublished($num);
        foreach($arrArticles as $articleId => $articleEntity) {
            $this->arrData[$articleId] = $this->createService($articleEntity);
        }

        return $this;
    }


    public function loadById(int $entityId) : ArticleService { return parent::loadById($entityId); }
    public function loadBySlugDashId(string $slugDashId) : ArticleService { return parent::loadBySlugDashId($slugDashId); }

    /**
     * @param ArticleService|null $entity
     */
    public function createService(?BaseEntity $entity = null) : ArticleService { return parent::createService($entity); }
}
