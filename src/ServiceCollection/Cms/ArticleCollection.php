<?php
namespace App\ServiceCollection\Cms;

use App\Service\Cms\Article;
use App\Service\Cms\Tag as TagService;
use App\Entity\Cms\Tag as TagEntity;
use DateTime;


class ArticleCollection extends BaseArticleCollection
{
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
        $sideArticlesNum    = min($sideArticlesNum, 5);

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


    public function loadLatestUpdatedListable(?int $page = 1)
    {
        $arrArticles = $this->getRepository()->findLatestUpdated($page);
        return $this->setEntities($arrArticles);
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


    public function loadTopViews(?int $page = 1, ?int $maxDaysAgo = null) : static
    {
        $arrArticles = $this->getRepository()->findTopViews($page, $maxDaysAgo);
        return $this->setEntities($arrArticles);
    }


    public function loadTopTopComments(?int $page = 1, ?int $maxDaysAgo = null) : static
    {
        $arrArticles = $this->getRepository()->findTopComments($page, $maxDaysAgo);
        return $this->setEntities($arrArticles);
    }


    public function loadGuidesForAuthors() : static
    {
        $this->loadComplete([
            Article::ID_ABOUT_US, Article::ID_ISSUE_REPORT, Article::ID_FORUM_IMAGES, Article::ID_HOW_TO_JOIN,
            Article::ID_HOW_TO_WRITE, Article::ID_PUBLISH_NEWS, Article::ID_PUBLISH_ARTICLE, Article::ID_SIGN_ARTICLE
        ]);

        $this->filterIfNotEmptyResult( fn(Article $article) => $article->isPublished() );
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
        if( $startDate > $endDate || ( !empty($maxDaysApart) && $startDate->diff($endDate)->days > $maxDaysApart )
        ) {
            throw new \DateInvalidOperationException();
        }

        $arrArticles = $this->getRepository()->getByPublishedDateInterval($startDate, $endDate);
        return $this->setEntities($arrArticles);
    }


    public function loadSerp(string $termToSearch) : static
    {
        $termToSearchNoStopWords = $this->factory->getStopWords()->removeFromSting($termToSearch);
        $arrArticles = $this->getRepository()->getSerp($termToSearchNoStopWords);
        return $this->setEntities($arrArticles);
    }


    public function loadPastYearsTitled(?int $page = 1) : static
    {
        $arrArticles = $this->getRepository()->findPastYearsTitled($page);
        return $this->setEntities($arrArticles);
    }


    public function loadForScheduling() : static
    {
        $arrArticles = $this->getRepository()->findForScheduling();
        return $this->setEntities($arrArticles);
    }
}
