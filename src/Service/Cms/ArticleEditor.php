<?php
namespace App\Service\Cms;

use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleTag;
use App\Service\Factory;
use App\Service\PhpBB\Topic;
use App\Service\TextProcessor;
use App\Service\User;
use DateTimeInterface;
use Exception;


class ArticleEditor extends Article
{
    protected HtmlProcessorReverse $htmlProcessorReverse;
    protected TextProcessor $textProcessor;


    public function __construct(Factory $factory)
    {
        parent::__construct($factory);
        $this->htmlProcessorReverse = new HtmlProcessorReverse($factory);
        $this->textProcessor        = new TextProcessor($this->htmlProcessorReverse);
    }


    public function addAuthor(User $author) : static
    {
        $this->entity->addAuthor(
            (new ArticleAuthor())
                ->setArticle($this->entity)
                ->setUser( $author->getEntity() )
        );

        return $this;
    }


    public function addTag(Tag $tag, User $user) : static
    {
        $this->entity->addTag(
            (new ArticleTag())
                ->setArticle($this->entity)
                ->setTag( $tag->getEntity() )
                ->setUser( $user->getEntity() )
        );

        return $this;
    }


    public function setTitle(string $newTitle) : static
    {
        $cleanTitle = $this->textProcessor->processRawInputTitleForStorage($newTitle);
        $this->entity->setTitle($cleanTitle);
        return $this;
    }


    public function setFormat(int $format) : static
    {
        $this->entity->setFormat($format);
        return $this;
    }


    public function setArchived(bool $archived) : static
    {
        $this->entity->setArchived($archived);
        return $this;
    }


    public function setBody(string $body) : static
    {
        $bodyForStorage = $this->htmlProcessorReverse->processArticleBodyForStorage($body);
        $this->entity->setBody($bodyForStorage);

        $spotlightId = $this->htmlProcessorReverse->getSpotlightId();
        if( empty($spotlightId) ) {

            $this->entity->setSpotlight(null);

        } else {

            try {
                $spotlight = $this->factory->createImage()->load($spotlightId)->getEntity();
                $this->entity->setSpotlight($spotlight);

            } catch(Exception) {
                $this->entity->setSpotlight(null);
            }
        }

        $abstract = $this->htmlProcessorReverse->getAbstract();
        $this->entity->setAbstract($abstract);

        return $this;
    }


    public function setCommentsTopic(Topic $topic) : static
    {
        $this->entity->setCommentsTopic( $topic->getEntity() );
        return $this;
    }


    public function setPublishedAt(?DateTimeInterface $publishedAt) : static
    {
        $this->entity->setPublishedAt($publishedAt);
        return $this;
    }


    public function setPublishingStatus(int $status) : static
    {
        $this->entity->setPublishingStatus($status);
        return $this;
    }


    public function setCommentTopicNeedsUpdate(int $status) : static
    {
        $this->entity->setCommentTopicNeedsUpdate($status);
        return $this;
    }


    public function save(bool $persist = true) : static
    {
        if( $this->entity->getCommentTopicNeedsUpdate() != static::COMMENT_TOPIC_UPDATE_NEVER ) {
            $this->entity->setCommentTopicNeedsUpdate(static::COMMENT_TOPIC_UPDATE_YES);
        }


        $title = $this->getTitle();
        if(
            stripos($title, 'Questa settimana su TLI') !== false ||
            stripos($title, 'Auguri di buone feste da TLI') !== false ||
            stripos($title, 'La storia di Windows, anno') !== false
        ) {
            $this->setArchived(true);
        }

        if($persist) {

            $this->factory->getEntityManager()->persist($this->entity);
            $this->factory->getEntityManager()->flush();
        }

        return $this;
    }
}
