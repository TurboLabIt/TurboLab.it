<?php
namespace App\ServiceCollection\Cms;

use App\Serializer\ArticleSearchNormalizer;
use App\Service\Cms\Article;
use App\Service\Cms\Tag;
use App\Service\Cms\Tag as TagService;
use App\Entity\Cms\Tag as TagEntity;
use App\Service\Factory;
use DateInvalidOperationException;
use DateTime;
use Meilisearch\Bundle\SearchService;


class ArticleCollection extends BaseArticleCollection
{
    public function __construct(Factory $factory, protected SearchService $searchService)
    {
        parent::__construct($factory);
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
            throw new DateInvalidOperationException();
        }

        $arrArticles = $this->getRepository()->getByPublishedDateInterval($startDate, $endDate);
        return $this->setEntities($arrArticles);
    }


    public function loadSerp(string $termToSearch) : static
    {
        $termToSearchNormalized     = ArticleSearchNormalizer::normalizeForIndexing($termToSearch);
        $termToSearchNoStopWords    = $this->factory->getStopWords()->removeFromSting($termToSearchNormalized);

        $arrArticles =
            $this->searchService->search(
                $this->factory->getEntityManager(), static::ENTITY_CLASS, $termToSearchNoStopWords
            );

        // we could setEntities($arrArticles) directly...
        // ... but then there would be no tags loaded => n+1 queries to generate the URLs
        $arrIds = [];
        foreach($arrArticles as $article) {
            $arrIds[] = $article->getId();
        }

        $arrArticles = $this->getRepository()->getByIdComplete($arrIds);
        return $this->setEntities($arrArticles);
    }


    public function loadPeriodicUpdateList(?int $page = 1) : static
    {
        $arrArticles = $this->getRepository()->findPeriodicUpdateList($page);
        return $this->setEntities($arrArticles);
    }


    public function loadForScheduling() : static
    {
        $arrArticles = $this->getRepository()->findForScheduling();
        return $this->setEntities($arrArticles);
    }


    public function loadNewOfTheYear(null|int|array $format = null) : static
    {
        $articles = $this->getRepository()->findNewOfTheYear($format);
        return $this->setEntities($articles);
    }


    public function loadNewOfTheYearWithTag(Tag $tag, ?int $num = null) : static
    {
        $this->loadNewOfTheYear();
        $this->filter(function(Article $article) use ($tag) {
            return $article->hasTag($tag);
        });

        if( !empty($num) ) {
            $this->arrData = $this->getItems($num);
        }

        return $this;
    }
}
