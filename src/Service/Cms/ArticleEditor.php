<?php
namespace App\Service\Cms;

use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleTag;
use App\Exception\ArticleUpdateException;
use App\Service\Factory;
use App\Service\PhpBB\Topic;
use App\Service\TextProcessor;
use App\Service\User;
use DateTimeInterface;
use Exception;


class ArticleEditor extends Article
{
    public function __construct(Factory $factory, protected TextProcessor $textProcessor)
    {
        parent::__construct($factory);
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


    public function setAuthorsFromIds(array $arrAuthorIds) : static
    {
        if( empty($arrAuthorIds) ) {
            throw new ArticleUpdateException('L\'articolo deve avere almeno 1 autore');
        }

        $arrAuthorIds = array_unique($arrAuthorIds);

        $collNewAuthors = $this->factory->createUserCollection()->load($arrAuthorIds);

        if( $collNewAuthors->count() < 1 ) {
            throw new ArticleUpdateException('L\'articolo deve avere almeno 1 autore');
        }

        // rebuild the cached author list
        $this->arrAuthors = [];

        $entityManager = $this->factory->getEntityManager();

        // these would be junctions (array-of-ArticleAuthor)
        $currentAuthors = $this->entity->getAuthors();

        // remove current authors who are no longer
        foreach($currentAuthors as $junction) {

            $authorId   = $junction->getUser()->getId();
            $newAuthor  = $collNewAuthors->get($authorId);

            if( empty($newAuthor) ) {
                $entityManager->remove($junction);
            }
        }

        // add new users
        $i=1;
        foreach($collNewAuthors as $author) {

            $existingJunction = null;
            foreach($currentAuthors as $junction) {

                if( $author->getId() == $junction->getUser()->getId() ) {

                    $existingJunction = $junction;
                    break;
                }
            }

            if( empty($existingJunction) ) {

                $existingJunction =
                    (new ArticleAuthor())
                        ->setArticle($this->entity)
                        ->setUser( $author->getEntity() );
            }

            $existingJunction->setRanking($i);
            $entityManager->persist($existingJunction);

            $i++;

            // rebuild the cached author list so that $article->getAuthors() works without a reload
            $userEntity                     = $author->getEntity();
            $authorId                       = $userEntity->getId();
            $this->arrAuthors[$authorId]    = $this->factory->createUser($userEntity);
        }

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
        $cleanBody = $this->textProcessor->processRawInputBodyForStorage($body);
        $this->entity->setBody($cleanBody);

        $spotlightId = $this->textProcessor->getSpotlightId();
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

        $abstract = $this->textProcessor->getAbstract();
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
