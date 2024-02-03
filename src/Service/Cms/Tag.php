<?php
namespace App\Service\Cms;

use App\Entity\BaseEntity;
use App\Entity\Cms\Tag as TagEntity;
use App\Trait\UrlableServiceTrait;
use App\Trait\ViewableServiceTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Tag extends BaseCmsService
{
    const ENTITY_CLASS          = TagEntity::class;
    const NOT_FOUND_EXCEPTION   = 'App\Exception\TagNotFoundException';

    use ViewableServiceTrait, UrlableServiceTrait;

    protected TagEntity $entity;


    public function __construct(protected TagUrlGenerator $urlGenerator, protected EntityManagerInterface $em)
    {
        $this->clear();
    }


    /**
     * @param TagEntity $entity
     */
    public function setEntity(BaseEntity $entity) : static
    {
        $this->localViewCount = $entity->getViews();
        return parent::setEntity($entity);
    }


    public function checkRealUrl($tagSlugDashId) : ?string
    {
        $candidateUrl   = '/' . $tagSlugDashId;
        $realUrl        = $this->urlGenerator->generateUrl($this, UrlGeneratorInterface::ABSOLUTE_PATH);
        $result         = $candidateUrl == $realUrl ? null : $this->getUrl();
        return $result;
    }


    public function getEntity() : TagEntity { return parent::getEntity(); }

    public function getTitle() : ?string { return $this->entity->getTitle(); }
    public function getSlug() : ?string { return $this->urlGenerator->buildSlug($this); }
    public function getAuthors() : Collection { return $this->entity->getAuthors(); }
    public function getArticles() : Collection { return $this->entity->getArticles(); }

    public function getUrl() : string { return $this->urlGenerator->generateUrl($this); }
}
