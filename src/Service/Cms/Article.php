<?php
namespace App\Service\Cms;

use App\Entity\BaseEntity;
use App\Entity\Cms\Article as ArticleEntity;
use App\Factory\Cms\ImageFactory;
use App\Factory\Cms\TagFactory;
use App\Trait\UrlableServiceTrait;
use App\Trait\ViewableServiceTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\Cms\Image as ImageService;


class Article extends BaseCmsService
{
    const ENTITY_CLASS          = ArticleEntity::class;
    const NOT_FOUND_EXCEPTION   = 'App\Exception\ArticleNotFoundException';

    use ViewableServiceTrait { countOneView as protected traitCountOneView; }
    use UrlableServiceTrait;

    protected ArticleEntity $entity;
    protected ?ImageService $spotlight;
    protected HtmlProcessor $htmlProcessor;


    public function __construct(
        protected ArticleUrlGenerator $urlGenerator, protected EntityManagerInterface $em,
        protected ImageFactory $imageFactory, protected TagFactory $tagFactory
    )
    {
        $this->clear();
    }


    /**
     * @param ArticleEntity $entity
     */
    public function setEntity(BaseEntity $entity) : static
    {
        $this->localViewCount = $entity->getViews();
        return parent::setEntity($entity);
    }


    public function getTopTag()
    {
        return null;
    }


    public function countOneView() : static
    {
        if( !$this->entity->publishingStatusCountOneView() ) {
            return $this;
        }

        return $this->traitCountOneView();
    }


    public function checkRealUrl($tagSlugDashId, $articleSlugDashId) : ?string
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


    public function getSpotlight() : ?ImageService
    {
        if( !empty($this->spotlight) ) {
            return $this->spotlight;
        }

        $spotlightEntity = $this->entity->getSpotlight();
        if( empty($spotlightEntity) ) {
            return null;
        }

        $this->spotlight = $this->imageFactory->create($spotlightEntity);
        return $this->spotlight;
    }


    public function getSpotlightOrDefault() : ImageService
    {
        $spotlight = $this->getSpotlight();
        if( !empty($spotlight) ) {
            return $spotlight;
        }

        return $this->imageFactory->createDefaultSpotlight();
    }


    public function getTags() : array
    {
        $tagJunctionEntities = $this->entity->getTags();
        $arrTags = [];
        foreach($tagJunctionEntities as $junctionEntity) {

            $tagEntity  = $junctionEntity->getTag();
            $tagId      = $tagEntity->getId();
            $arrTags[$tagId] = [
                "Tag"   => $this->tagFactory->create($tagEntity)
            ];
        }

        return $arrTags;
    }


    public function setHtmlProcessor(HtmlProcessor $htmlProcessor) : static
    {
        $this->htmlProcessor = $htmlProcessor;
        return $this;
    }


    public function getBodyForDisplay() : ?string
    {
        return $this->htmlProcessor->processArticleBodyForDisplay($this);
    }


    public function getEntity() : ArticleEntity { return parent::getEntity(); }

    public function getTitle() : ?string { return $this->entity->getTitle(); }
    public function getSlug() : ?string { return $this->urlGenerator->buildSlug($this); }
    public function getAuthors() : Collection { return $this->entity->getAuthors(); }
    public function getPublishedAt() : ?\DateTime { return $this->entity->getPublishedAt(); }
    public function getUpdatedAt() : ?\DateTime { return $this->entity->getUpdatedAt(); }

    public function getAbstract() : ?string { return $this->entity->getAbstract(); }
    public function getBody() : ?string { return $this->entity->getBody(); }

    public function getCommentsUrl() : ?string { return $this->urlGenerator->generateArticleCommentsUrl($this); }
}