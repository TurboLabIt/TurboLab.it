<?php
namespace App\ServiceCollection\Cms;

use App\Repository\Cms\ArticleRepository;
use App\Service\Cms\Article;
use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\Tag as TagService;
use App\Entity\Cms\Tag as TagEntity;
use App\Service\User as UserService;
use App\Entity\PhpBB\User as UserEntity;
use App\ServiceCollection\BaseServiceEntityCollection;
use DateTime;


class ArticleCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = Article::ENTITY_CLASS;


    public function getRepository() : ArticleRepository
    {
        /** @var ArticleRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(ArticleEntity::class);
        return $repository;
    }


    public function loadAllPublished() : static
    {
        $arrArticles = $this->getRepository()->findAllPublished();
        return $this->setEntities($arrArticles);
    }


    public function loadLatestPublished(?int $page = 1) : static
    {
        $arrArticles = $this->getRepository()->findLatestPublished($page);
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


    public function loadDrafts(?int $page = 1) : static
    {
        $arrArticles = $this->getRepository()->findDrafts($page);
        return $this->setEntities($arrArticles);
    }


    public function loadLatestReadyForReview(?int $page = 1) : static
    {
        $arrArticles = $this->getRepository()->findLatestReadyForReview($page);
        return $this->setEntities($arrArticles);
    }


    public function loadByTag(TagEntity|TagService $tag, ?int $page = 1) : static
    {
        $tag = $tag instanceof TagService ? $tag->getEntity() : $tag;
        $paginator = $this->getRepository()->findByTag($tag, $page) ?? [];
        return $this->setEntities($paginator);
    }


    public function loadByAuthor(UserEntity|UserService $user, ?int $page = 1) : static
    {
        $user = $user instanceof UserService ? $user->getEntity() : $user;
        $paginator = $this->getRepository()->findByAuthor($user, $page) ?? [];
        return $this->setEntities($paginator);
    }


    public function loadLatestForNewsletter() : static
    {
        $arrArticles = $this->getRepository()->findLatestForNewsletter();
        return $this->setEntities($arrArticles);
    }


    public function loadLatestForSocialSharing(int $maxPublishedMinutes) : static
    {
        $arrArticles = $this->getRepository()->findLatestForSocialSharing($maxPublishedMinutes);
        return $this->setEntities($arrArticles);
    }


    public function loadLatestNewsPublished(?int $page = 1) : static
    {
        $arrArticles = $this->getRepository()->findLatestNewsPublished($page);
        return $this->setEntities($arrArticles);
    }


    public function loadLatestSecurityNews(?int $num = null) : static
    {
        $arrArticles = $this->getRepository()->findLatestSecurityNews($num);
        return $this->setEntities($arrArticles);
    }


    public function loadTopViewsRecent(?int $page = 1) : static
    {
        $arrArticles = $this->getRepository()->findTopViewsLastYear($page);
        return $this->setEntities($arrArticles);
    }


    public function loadTopViews(?int $page = 1) : static
    {
        $arrArticles = $this->getRepository()->findTopViews($page);
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
        $arrArticles = $this->getRepository()->getPrevNextArticle( $article->getEntity() );
        return $this->setEntities($arrArticles);
    }


    public function loadRandom(?int $num = null) : static
    {
        $arrArticles = $this->getRepository()->getRandomComplete($num);
        return $this->setEntities($arrArticles);
    }


    public function loadFirstAndLastPublished() : static
    {
        $arrArticles = $this->getRepository()->getFirstAndLastPublished();
        return $this->setEntities($arrArticles);
    }


    public function loadByPublishedDateInterval(DateTime $startDate, DateTime $endDate, ?int $maxDaysApart = 45) : static
    {
        if(
            empty($startDate) || empty($endDate) || $startDate > $endDate ||
            ( !empty($maxDaysApart) && $startDate->diff($endDate)->days > $maxDaysApart )
        ) {
            throw new \DateInvalidOperationException();
        }

        $arrArticles = $this->getRepository()->getByPublishedDateInterval($startDate, $endDate);
        return $this->setEntities($arrArticles);
    }


    public function createService(?ArticleEntity $entity = null) : Article { return $this->factory->createArticle($entity); }
}
