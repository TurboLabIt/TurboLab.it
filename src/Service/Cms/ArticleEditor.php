<?php
namespace App\Service\Cms;

use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleImage;
use App\Entity\Cms\ArticleTag;
use App\Entity\Cms\Tag as TagEntity;
use App\Entity\Cms\Image as ImageEntity;
use App\Entity\PhpBB\Forum;
use App\Service\Factory;
use App\Service\PhpBB\Topic;
use App\Entity\PhpBB\Topic as TopicEntity;
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


    //<editor-fold defaultstate="collapsed" desc="*** 📜 Title and Body ***">
    public function setTitle(string $newTitle) : static
    {
        $cleanTitle = $this->textProcessor->processRawInputTitleForStorage($newTitle);
        $this->entity->setTitle($cleanTitle);
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
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** ℹ️ Attributes ***">
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


    public function excludeFromPeriodicUpdateList(bool $exclude = true) : static
    {
        $this->entity->excludeFromPeriodicUpdateList($exclude);
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


    public function setCommentsTopicNeedsUpdate(int $status) : static
    {
        $this->entity->setCommentsTopicNeedsUpdate($status);
        return $this;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 👥 Authors ***">
    public function addAuthor(User $author) : static
    {
        // invalidate the cached author list
        $this->arrAuthors = null;

        $this->entity->addAuthor(
            (new ArticleAuthor())
                ->setArticle($this->entity)
                ->setUser( $author->getEntity() )
        );

        return $this;
    }


    public function setAuthors(iterable $newAuthors, User $author) : static
    {
        // invalidate the cached author list
        $this->arrAuthors = null;

        $oldAuthorJunctions = $this->entity->getAuthors()->getValues();
        foreach($oldAuthorJunctions as $junction) {
            $this->entity->removeAuthor($junction);
        }

        $oldAuthorJunctionByAuthorId = [];
        foreach($oldAuthorJunctions as $junction) {

            $authorId = $junction->getUser()->getId();
            $oldAuthorJunctionByAuthorId[$authorId] = $junction;
        }

        foreach($newAuthors as $user) {

            $userId = $user->getId();
            $existingAuthorJunction = $oldAuthorJunctionByAuthorId[$userId] ?? null;

            if( empty($existingAuthorJunction) ) {

                $this->addAuthor($user);

            } else {

                $this->entity->addAuthor($existingAuthorJunction);
            }
        }

        return $this;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🏷️ Tags ***">
    public function addTag(Tag|TagEntity $tag, User $author) : static
    {
        $tagToAdd = $tag instanceof Tag ? $tag->getEntity() : $tag;

        $this->entity->addTag(
            (new ArticleTag())
                ->setTag($tagToAdd)
                ->setUser( $author->getEntity() )
        );

        return $this;
    }


    public function setTags(iterable $newTags, User $author) : static
    {
        // invalidate the cached tag list
        $this->arrTags = null;

        $oldTagJunctions = $this->entity->getTags()->getValues();
        foreach($oldTagJunctions as $junction) {
            $this->entity->removeTag($junction);
        }

        $oldTagJunctionByTagId = [];
        foreach($oldTagJunctions as $junction) {

            $tagId = $junction->getTag()->getId();
            $oldTagJunctionByTagId[$tagId] = $junction;
        }

        foreach($newTags as $tag) {

            $tagId = $tag->getId();
            $existingTagJunction = $oldTagJunctionByTagId[$tagId] ?? null;

            if( empty($existingTagJunction) ) {

                $this->addTag($tag, $author);

            } else {

                $this->entity->addTag($existingTagJunction);
            }
        }

        return $this;
    }


    public function autotag(User $user) : static
    {
        $title = $this->getTitle();
        $titleNoStopWords = $this->factory->getStopWords()->removeFromSting($title);
        $arrTitleWords = preg_split('/[^a-zA-Z0-9]+/', $titleNoStopWords, -1, PREG_SPLIT_NO_EMPTY);

        $arrNoAutoTags = [Tag::ID_DEFAULT_TAG, Tag::ID_TEST_NO_ARTICLES, Tag::ID_NEWSLETTER_TLI];

        $tags =
            $this->factory->createTagCollection()->loadBySearch($arrTitleWords, false)
                ->filter(function(Tag $tag) use ($arrNoAutoTags) { return !in_array($tag->getId(), $arrNoAutoTags); });

        $arrTitleWordsCombined = $arrTitleWords;
        for($i = 0; $i < count($arrTitleWords) - 1; $i++) {
            // the separator is just for visual clarity - it will be removed before the comparison
            $arrTitleWordsCombined[] = $arrTitleWords[$i] . "|" . $arrTitleWords[$i+1];
        }

        $arrAddedTag = [];

        foreach($arrTitleWordsCombined as $candidate) {

            if( empty($candidate) || empty( trim($candidate) ) || mb_strlen($candidate) < 2 ) {
                continue;
            }

            $arrTagToAdd =
                $tags->lookupSearchExtract(
                    $this->factory->createTagEditor()->setTitle($candidate),
                    function(Tag $candidateTag, Tag $realTag) {
                        return $candidateTag->getTitleComparable() == $realTag->getTitleComparable();
                    }
                );

            /** @var Tag $tagToAdd */
            foreach($arrTagToAdd as $tagToAdd) {

                $tagId              = $tagToAdd->getId();
                $replacementTag     = $tagToAdd->getReplacement();
                $replacementTagId   = $replacementTag?->getId();

                if(
                    in_array($tagId, $arrAddedTag) ||
                    ( !empty($replacementTagId) && in_array($replacementTagId, $arrAddedTag) )
                ) {
                    continue;
                }

                $arrAddedTag[] = $tagId;
                if( !empty($replacementTagId) ) {
                    $arrAddedTag[] = $replacementTagId;
                }

                $tagToAdd = $replacementTag ?? $tagToAdd;
                $this->addTag($tagToAdd, $user);
            }
        }

        return $this;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🖼️ Images ***">
    public function addImage(Image|ImageEntity $image, User $author) : static
    {
        $imageToAdd = $image instanceof Image ? $image->getEntity() : $image;

        $this->entity->addImage(
            (new ArticleImage())
                ->setImage($imageToAdd)
        );

        return $this;
    }

    public function addImages(iterable $images, User $author) : static
    {
        foreach($images as $image) {
            $this->addImage($image, $author);
        }

        return $this;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 💬 Comments topic ***">
    public function createCommentsTopicPlaceholder() : static
    {
        if( !$this->isVisitable() || !empty( $this->getCommentsTopic() ) ) {
            return $this;
        }

        $newTopicEntity =
            $this->factory->getEntityManager()->getRepository(TopicEntity::class)
                ->insertNewRow(
                    Topic::buildCommentsTitle( $this->getTitle(), $this->getId() ),
                    'Post in preparazione...'
                    , Forum::ID_COMMENTS);

        $commentsTopic = $this->factory->createTopic($newTopicEntity);
        return $this->setCommentsTopic($commentsTopic);
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 💾 Save ***">
    public function save(bool $persist = true) : static
    {
        if( $this->entity->getCommentsTopicNeedsUpdate() != static::COMMENTS_TOPIC_NEEDS_UPDATE_NEVER ) {
            $this->entity->setCommentsTopicNeedsUpdate(static::COMMENTS_TOPIC_NEEDS_UPDATE_YES);
        }

        $title = $this->getTitle();
        if(
            $this->entity->isNewsletter() ||
            stripos($title, 'Auguri di buone feste da TLI') !== false ||
            stripos($title, 'La storia di Windows, ann') !== false
        ) {
            $this->excludeFromPeriodicUpdateList();
        }

        if($persist) {

            $this->factory->getEntityManager()->persist($this->entity);
            $this->factory->getEntityManager()->flush();
        }

        return $this;
    }
    //</editor-fold>
}
