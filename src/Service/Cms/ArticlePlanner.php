<?php
namespace App\Service\Cms;

use App\Service\Factory;
use App\Service\Mailer;
use App\ServiceCollection\Cms\ArticleCollection;
use DateTime;
use DateTimeZone;


class ArticlePlanner
{
    protected ?ArticleCollection $articles;


    public function __construct(protected Factory $factory, protected Mailer $mailer) {}


    public function setPublishingStatus(ArticleEditor $article, int $publishingStatus, bool $publishUrgently) : static
    {
        $sentinel = $this->factory->createArticleSentinel($article);
        $sentinel->enforceCanSetPublishingStatusTo($publishingStatus);

        $publishedAt = $article->getPublishedAt();

        //
        if(
            !empty($publishedAt) && $publishedAt > (new DateTime()) &&
            in_array($publishingStatus, ArticleEditor::PUBLISHING_STATUSES_VISIBLE)
        ) {
            return $this->publishOnDate($article, $publishedAt);
        }

        //
        if( !empty($publishedAt) && $publishedAt < (new DateTime('today midnight')) ) {
            $article->setPublishedAt(null);
        }

        //
        if( $article->isNews() && $publishingStatus == ArticleEditor::PUBLISHING_STATUS_PUBLISHED ) {
            return $this->publishOnDate($article, new DateTime());
        }

        //
        if(
            $article->isNews() &&
            in_array($publishingStatus, ArticleEditor::PUBLISHING_STATUSES_VISIBLE) &&
            $sentinel->getCurrentUser()->isEditor()
        ) {
            return $this->publishOnDate($article, new DateTime());
        }

        //
        if( $publishingStatus != ArticleEditor::PUBLISHING_STATUS_PUBLISHED ) {

            $article
                ->setPublishingStatus($publishingStatus)
                ->save();

            return $this;
        }


        //
        if($publishUrgently) {
            return $this->publishNextBusinessDay($article);
        }

        return $this->publishScheduled($article);
    }


    protected function publishOnDate(ArticleEditor $article, DateTime $publishingDate) : static
    {
        $article
            ->setPublishingStatus(ArticleEditor::PUBLISHING_STATUS_PUBLISHED)
            ->setPublishedAt($publishingDate)
            ->save();

        $this->mailer
            ->buildArticlePublished($article, $this->factory->getCurrentUser() )
            ->block(false)
            ->send();

        return $this;
    }


    protected function publishScheduled(ArticleEditor $article) : static
    {
        $publishingDate = $this->findPublishingDate();
        return $this->publishOnDate($article, $publishingDate);
    }


    protected function findPublishingDate() : DateTime
    {
        $publishingDate = new DateTime();
        if( (int)date('H') > 12 ) {
            $publishingDate->modify('tomorrow midnight');
        }

        while(true) {

            if( $this->isDateUsableForNewPublishing($publishingDate) ) {
                break;
            }

            $publishingDate->modify('+1 day')->setTime(0, 0);
        }

        return $publishingDate;
    }


    protected function getScheduledArticlesCollection() : ArticleCollection
    {
        if( !empty($this->articles) ) {
            return $this->articles;
        }

        return $this->articles = $this->factory->createArticleCollection()->loadForScheduling();
    }


    protected function isDateUsableForNewPublishing(DateTime $candidate) : bool
    {
        if( !$this->isBusinessDay($candidate) ) {
            return false;
        }

        $candidateYmd = $candidate->format('Y-m-d');

        $articles = $this->getScheduledArticlesCollection();

        $existingArticle =
            $articles->getFilteredData( function(Article $article) use($candidateYmd) {
                return $article->getPublishedAt()->format('Y-m-d') == $candidateYmd;
            });

        return empty($existingArticle);
    }


    protected function isBusinessDay(DateTime $date) : bool
    {
        // saturday and sunday are NOT business days for TLI
        if( in_array($date->format('w'),  [6, 0]) ) {
            return false;
        }

        $easter = DateTime::createFromFormat('U', easter_date($date->format('Y')));
        $easter->setTimezone(new DateTimeZone('Europe/Rome'));
        $easterMonday = (clone $easter)->modify('next monday');

        return
            !in_array($date->format('m-d'), [
                '01-01', '01-06',
                $easter->format('m-d'), $easterMonday->format('m-d'),
                '04-25', '05-01', '06-02',
                '08-15',
                '11-01',
                '12-08', '12-25', '12-26'
            ]);
    }


    protected function publishNextBusinessDay(ArticleEditor $article) : static
    {
        $articles = $this->getScheduledArticlesCollection();
        $now = new DateTime();

        $arrArticlesOfEarlierToday =
            $articles->getFilteredData( function(Article $article) use($now) {
                return
                    $article->getPublishedAt()->format('Y-m-d') == $now->format('Y-m-d') &&
                    $article->getPublishedAt() < $now;
            });

        $arrArticlesOfLaterToday =
            $articles->getFilteredData( function(Article $article) use($now) {
                return
                    $article->getPublishedAt()->format('Y-m-d') == $now->format('Y-m-d') &&
                    $article->getPublishedAt() > $now;
            });


        if( (int)date('H') < 12 && empty($arrArticlesOfEarlierToday) && empty($arrArticlesOfLaterToday) ) {

            $this->publishOnDate($article, new DateTime());
            return $this;
        }

        // it's unusual for an article to be published later today. It there is, it could be important
        // --> skipped working on $arrArticlesOfLaterToday

        $publishingDate = (new DateTime())->modify('tomorrow midnight');

        while(true) {

            if( $this->isBusinessDay($publishingDate) ) {
                break;
            }

            $publishingDate->modify('+1 day');
        }

        $this->publishOnDate($article, $publishingDate);

        $arrArticlesToShift =
            $articles->getFilteredData( function(Article $article) use($publishingDate) {
                return $article->getPublishedAt()->format('Y-m-d') == $publishingDate->format('Y-m-d');
            });

        foreach($arrArticlesToShift as $articleToShift) {

            $publishingDate = $this->findPublishingDate();
            $this->factory->createArticleEditor( $articleToShift->getEntity() )
                ->setPublishedAt($publishingDate)
                ->save();

            //
            $this->articles = null;
        }

        return $this;
    }
}
