<?php
namespace App\Service\Cms;

use App\Entity\BaseEntity;
use App\Entity\Cms\Article as ArticleEntity;
use App\Factory\Cms\ImageFactory;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\Cms\Image as ImageService;


class Article extends BaseCmsService
{
    protected ArticleEntity $entity;
    protected int $localViewCount = 0;
    protected ?ImageService $spotlight;


    public function __construct(
        protected ArticleUrlGenerator $urlGenerator, protected EntityManagerInterface $em,
        protected ImageFactory $imageFactory
    )
    {
        $this->entity = new ArticleEntity();
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

        $this->localViewCount ++;

        $this->em->getRepository(ArticleEntity::class)->countOneView( $this->getId() );
        return $this;
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


    public function getEntity() : ArticleEntity { return parent::getEntity(); }

    public function getTitle() : ?string { return $this->entity->getTitle(); }
    public function getAuthors() : Collection { return $this->entity->getAuthors(); }
    public function getTags() : Collection { return $this->entity->getTags(); }
    public function getPublishedAt() : ?\DateTime { return $this->entity->getPublishedAt(); }
    public function getUpdatedAt() : ?\DateTime { return $this->entity->getUpdatedAt(); }

    public function getAbstract() : ?string { return $this->entity->getAbstract(); }
    public function getBody() : ?string { return $this->entity->getBody(); }

    public function getViews() : int { return $this->localViewCount; }
    public function getUrl() : string { return $this->urlGenerator->generateUrl($this); }
    public function getCommentsUrl() : ?string { return $this->urlGenerator->generateArticleCommentsUrl($this); }
}
