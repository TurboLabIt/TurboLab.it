<?php
namespace App\ServiceCollection\Cms;

use App\Service\Cms\Article;
use App\Service\Cms\Article as ArticleService;
use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\Tag as TagService;
use App\Entity\Cms\Tag as TagEntity;


class ArticleCollection extends BaseCmsServiceCollection
{
    const string ENTITY_CLASS = ArticleService::ENTITY_CLASS;


    public function load(array $arrIds) : array
    {
        $arrEntities = $this->em->getRepository(static::ENTITY_CLASS)->findMultipleComplete($arrIds);
        return $this->setEntities($arrEntities)->arrData;
    }


    public function loadAllPublished() : static
    {
        $arrTopics = $this->em->getRepository(static::ENTITY_CLASS)->findAllPublished();
        return $this->setEntities($arrTopics);
    }


    public function loadLatestPublished(?int $page = 1) : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->findLatestPublished($page);
        return $this->setEntities($arrArticles);
    }


    public function loadLatestReadyForReview() : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->findLatestReadyForReview();
        return $this->setEntities($arrArticles);
    }


    public function loadByTag(TagEntity|TagService $tag, ?int $page = 1) : static
    {
        $tag = $tag instanceof TagService ? $tag->getEntity() : $tag;
        $paginator = $this->em->getRepository(static::ENTITY_CLASS)->findByTag($tag, $page) ?? [];
        return $this->setEntities($paginator);
    }


    public function loadLatestForNewsletter() : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->findLatestForNewsletter();
        return $this->setEntities($arrArticles);
    }


    public function loadLatestForSocialSharing(int $maxPublishedMinutes) : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->findLatestForSocialSharing($maxPublishedMinutes);
        return $this->setEntities($arrArticles);
    }


    public function loadLatestNewsPublished(?int $page = 1) : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->findLatestNewsPublished($page);
        return $this->setEntities($arrArticles);
    }


    public function loadLatestSecurityNews() : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->findLatestSecurityNews();
        return $this->setEntities($arrArticles);
    }


    public function loadTopViewsRecent(?int $page = 1) : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->findTopViewsLastYear($page);
        return $this->setEntities($arrArticles);
    }


    public function loadGuidesForAuthors() : static
    {
        $this->load([
            Article::ID_ABOUT_US, Article::ID_ISSUE_REPORT, Article::ID_FORUM_IMAGES, Article::ID_HOW_TO_JOIN,
            Article::ID_HOW_TO_WRITE, Article::ID_PUBLISH_NEWS, Article::ID_PUBLISH_ARTICLE, Article::ID_SIGN_ARTICLE
        ]);
        return $this;
    }


    public function createService(?ArticleEntity $entity = null) : ArticleService { return $this->factory->createArticle($entity); }
}
