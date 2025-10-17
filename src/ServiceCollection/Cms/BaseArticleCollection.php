<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Article as ArticleEntity;
use App\Repository\Cms\ArticleRepository;
use App\Service\Cms\Article;
use App\ServiceCollection\BaseServiceEntityCollection;


abstract class BaseArticleCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = Article::ENTITY_CLASS;

    public function getRepository() : ArticleRepository { return $this->em->getRepository(static::ENTITY_CLASS); }

    public function createService(?ArticleEntity $entity = null) : Article { return $this->factory->createArticle($entity); }
}
