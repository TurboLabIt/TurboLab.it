<?php
namespace App\Service\Cms;

use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleTag;
use App\Service\Factory;
use App\Service\PhpBB\Topic;
use App\Service\User;
use Doctrine\ORM\EntityManagerInterface;


class ArticleEditor extends Article
{
    protected HtmlProcessorReverse $htmlProcessorReverse;


    public function __construct(ArticleUrlGenerator $urlGenerator, EntityManagerInterface $em, Factory $factory)
    {
        parent::__construct($urlGenerator, $em, $factory);
        $this->htmlProcessorReverse = new HtmlProcessorReverse($factory);
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
        $this->entity->setTitle($newTitle);
        return $this;
    }


    public function setFormat(int $format) : static
    {
        $this->entity->setFormat($format);
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

            } catch(\Exception) {
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


    public function setPublishedAt(?\DateTimeInterface $publishedAt) : static
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


    public function save() : static
    {
        if( $this->entity->getCommentTopicNeedsUpdate() != static::COMMENT_TOPIC_UPDATE_NEVER ) {
            $this->entity->setCommentTopicNeedsUpdate(static::COMMENT_TOPIC_UPDATE_YES);
        }

        $this->em->persist($this->entity);
        $this->em->flush();

        return $this;
    }
}
