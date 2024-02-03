<?php
namespace App\ServiceCollection\Cms;

use App\Entity\BaseEntity;
use App\Factory\Cms\ArticleFactory;
use App\Service\Cms\Article as ArticleService;
use App\Entity\Cms\Article as ArticleEntity;
use Doctrine\ORM\EntityManagerInterface;


class ArticleCollection extends BaseCmsServiceCollection
{
    const ENTITY_CLASS = ArticleService::ENTITY_CLASS;


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


    /**
     * @param ArticleEntity|null $entity
     */
    public function createService(?BaseEntity $entity = null) : ArticleService { return parent::createService($entity); }
}
