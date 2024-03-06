<?php
namespace App\Service\Cms;

use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleTag;
use App\Service\PhpBB\Topic;
use App\Service\User;


class ArticleEditor extends Article
{
    public function save() : static
    {
        $this->em->persist($this->entity);
        $this->em->flush();
        return $this;
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
        $this->entity->setBody($body);
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
}
