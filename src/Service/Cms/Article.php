<?php
namespace App\Service\Cms;

use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\Image as ImageService;
use App\Service\Cms\Tag as TagService;
use App\Service\Factory;
use App\Trait\ArticleFormatsTrait;
use App\Trait\PublishingStatusesTrait;
use App\Trait\UrlableServiceTrait;
use App\Trait\ViewableServiceTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Article extends BaseCmsService
{
    const string ENTITY_CLASS           = ArticleEntity::class;
    const string NOT_FOUND_EXCEPTION    = 'App\Exception\ArticleNotFoundException';

    // 👀 https://turbolab.it/402
    const int ID_NEWSLETTER = 402;

    use ViewableServiceTrait { countOneView as protected traitCountOneView; }
    use UrlableServiceTrait, PublishingStatusesTrait, ArticleFormatsTrait;

    protected ?ArticleEntity $entity = null;
    protected ?ImageService $spotlight;
    protected HtmlProcessor $htmlProcessor;
    protected ?TagService $topTag = null;


    public function __construct(protected ArticleUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected Factory $factory)
    {
        $this->clear();
        $this->htmlProcessor = new HtmlProcessor($factory);
    }


    public function setEntity(?ArticleEntity $entity = null) : static
    {
        $this->localViewCount = $entity->getViews();
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?ArticleEntity { return $this->entity; }


    public function getTopTag() : ?TagService
    {
        if( !empty($this->topTag) ) {
            return $this->topTag;
        }

        $arrTags = $this->getTags();
        if( empty($arrTags) ) {
            return null;
        }

        $this->topTag = reset($arrTags);

        /** @var TagService $topTagCandidate */
        foreach($arrTags as $topTagCandidate) {

            if( $topTagCandidate->getRanking() > $this->topTag->getRanking() ) {
                $this->topTag = $topTagCandidate;
            }
        }

        return $this->topTag;
    }


    public function countOneView() : static
    {
        if( !$this->entity->publishingStatusCountOneView() ) {
            return $this;
        }

        return $this->traitCountOneView();
    }


    public function checkRealUrl(string $tagSlugDashId, string $articleSlugDashId) : ?string
    {
        $candidateUrl   = '/' . $tagSlugDashId . '/' . $articleSlugDashId;
        $realUrl        = $this->urlGenerator->generateUrl($this, UrlGeneratorInterface::ABSOLUTE_PATH);
        $result         = $candidateUrl == $realUrl ? null : $this->getUrl();
        return $result;
    }


    public function getFeedGuId() : string
    {
        $guid =
            implode(',', array_filter([
                $this->entity->getId(),  $this->entity->getPublishingStatus(),
                $this->getPublishedAt()?->format('Y-m-d-H:i:s')
            ]));

        return $guid;
    }


    public function getSpotlightOrDefaultUrl(string $size) : string
    {
        return $this->getSpotlightOrDefault()->getUrl($this, $size);
    }


    public function getSpotlightUrl(string $size) : ?string
    {
        return $this->getSpotlight()?->getUrl($this, $size);
    }


    public function getSpotlight() : ?ImageService
    {
        if( !empty($this->spotlight) ) {
            return $this->spotlight;
        }

        $spotlightEntity = $this->entity->getSpotlight();
        if( empty($spotlightEntity) ) {
            return null;
        }

        $this->spotlight = $this->factory->createImage($spotlightEntity);
        return $this->spotlight;
    }


    public function getSpotlightOrDefault() : ImageService
    {
        $spotlight = $this->getSpotlight();
        if( !empty($spotlight) ) {
            return $spotlight;
        }

        return $this->factory->createDefaultSpotlight();
    }


    public function getBodyForDisplay() : ?string
    {
        return $this->htmlProcessor->processArticleBodyForDisplay($this);
    }


    public function getTags() : array
    {
        $tagJunctionEntities = $this->entity->getTags();
        $arrTags = [];
        foreach($tagJunctionEntities as $junctionEntity) {

            $tagEntity  = $junctionEntity->getTag();
            $tagId      = $tagEntity->getId();
            $arrTags[$tagId] = $this->factory->createTag($tagEntity);
        }

        return $arrTags;
    }


    public function getFiles() : array
    {
        $fileJunctionEntities = $this->entity->getFiles();
        $arrFiles = [];
        foreach($fileJunctionEntities as $junctionEntity) {

            $fileEntity = $junctionEntity->getFile();
            $fileId     = $fileEntity->getId();
            $arrFiles[$fileId] = $this->factory->createFile($fileEntity);
        }

        return $arrFiles;
    }


    public function getAuthors() : Collection { return $this->entity->getAuthors(); }
    public function getPublishedAt() : ?\DateTimeInterface { return $this->entity->getPublishedAt(); }

    public function getAbstract() : ?string { return $this->entity->getAbstract(); }
    public function getBody() : ?string { return $this->entity->getBody(); }

    public function getCommentsUrl() : ?string { return $this->urlGenerator->generateArticleCommentsUrl($this); }
}
