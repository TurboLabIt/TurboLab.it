<?php
namespace App\ServiceCollection\Cms;

use App\Service\Cms\Article as ArticleService;
use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\CmsFactory;
use App\Service\Cms\HtmlProcessor;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Cms\Tag as TagService;
use App\Entity\Cms\Tag as TagEntity;


class ArticleCollection extends BaseCmsServiceCollection
{
    const string ENTITY_CLASS = ArticleService::ENTITY_CLASS;


    public function __construct(protected EntityManagerInterface $em, protected CmsFactory $factory)
    { }


    public function setHtmlProcessor(HtmlProcessor $htmlProcessor) : static
    {
        $this->iterate( fn($item) => $item->setHtmlProcessor($htmlProcessor) );
        return $this;
    }


    public function loadLatestPublished(int $num) : static
    {
        $arrArticles = $this->em->getRepository(ArticleEntity::class)->findLatestPublished($num);
        return $this->setEntities($arrArticles);
    }


    public function loadLatestReadyForReview() : static
    {
        $arrArticles = $this->em->getRepository(ArticleEntity::class)->findLatestReadyForReview();
        return $this->setEntities($arrArticles);
    }


    public function loadByTag(TagEntity|TagService $tag, ?int $page = 1) : static
    {
        $tag = $tag instanceof TagService ? $tag->getEntity() : $tag;
        $paginator = $this->em->getRepository(ArticleEntity::class)->findByTag($tag, $page) ?? [];
        return $this->setEntities($paginator);
    }


    public function createService(?ArticleEntity $entity = null) : ArticleService { return $this->factory->createArticle($entity); }
}
