<?php
namespace App\ServiceCollection\Cms;

use App\Service\Cms\Article;
use App\Service\Cms\Article as ArticleService;
use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\Tag as TagService;
use App\Entity\Cms\Tag as TagEntity;
use App\Service\User as UserService;
use App\Entity\PhpBB\User as UserEntity;
use App\ServiceCollection\BaseServiceEntityCollection;


class ArticleCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = ArticleService::ENTITY_CLASS;


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

    public function loadSideBarOf(Article $article) : static
    {
        $this->loadLatestPublished();

        $sideArticlesNum    = $article->textLengthIndex() / 10;
        $sideArticlesNum    = $sideArticlesNum < 5 ? $sideArticlesNum : 5;

        $arrFilteredArticles = [];
        foreach($this->arrData as $articleInSetId => $articleInSet) {

            if( $articleInSetId == $article->getId() || $articleInSet->isNewsletter() ) {
                continue;
            }

            $arrFilteredArticles[$articleInSetId] = $articleInSet;

            if( count($arrFilteredArticles) >= $sideArticlesNum ) {
                break;
            }
        }

        $this->arrData = $arrFilteredArticles;

        return $this;
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


    public function loadByAuthor(UserEntity|UserService $user, ?int $page = 1) : static
    {
        $user = $user instanceof UserService ? $user->getEntity() : $user;
        $paginator = $this->em->getRepository(static::ENTITY_CLASS)->findByAuthor($user, $page) ?? [];
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


    public function loadLatestSecurityNews(?int $num = null) : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->findLatestSecurityNews($num);
        return $this->setEntities($arrArticles);
    }


    public function loadTopViewsRecent(?int $page = 1) : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->findTopViewsLastYear($page);
        return $this->setEntities($arrArticles);
    }


    public function loadGuidesForAuthors() : static
    {
        $this->loadComplete([
            Article::ID_ABOUT_US, Article::ID_ISSUE_REPORT, Article::ID_FORUM_IMAGES, Article::ID_HOW_TO_JOIN,
            Article::ID_HOW_TO_WRITE, Article::ID_PUBLISH_NEWS, Article::ID_PUBLISH_ARTICLE, Article::ID_SIGN_ARTICLE
        ]);
        return $this;
    }


    public function loadPrevNextArticle(Article $article) : static
    {
        $arrArticles = $this->em->getRepository(static::ENTITY_CLASS)->getPrevNextArticle( $article->getEntity() );
        return $this->setEntities($arrArticles);
    }


    public function createService(?ArticleEntity $entity = null) : ArticleService { return $this->factory->createArticle($entity); }
}
