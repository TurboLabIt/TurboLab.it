<?php
namespace App\Service\Cms;

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

    protected ?TagEntity $entity    = null;
    protected ?array $arrArticles   = null;


    public function __construct(protected TagUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected CmsFactory $factory)
    {
        $this->clear();
    }


    public function setEntity(?TagEntity $entity = null) : static
    {
        $this->localViewCount = $entity->getViews();
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?TagEntity { return $this->entity; }


    public function checkRealUrl(string $tagSlugDashId, ?int $page = null) : ?string
    {
        $pageSlug       = empty($page) || $page < 2 ? null : ("/$page");
        $candidateUrl   = '/' . $tagSlugDashId . $pageSlug;
        $realUrl        = $this->urlGenerator->generateUrl($this, $page, UrlGeneratorInterface::ABSOLUTE_PATH);
        $result         = $candidateUrl == $realUrl ? null : $this->getUrl();
        return $result;
    }


    public function loadByTitle(string $title) : static
    {
        $this->clear();
        $entity = $this->em->getRepository(static::ENTITY_CLASS)->findByTitle($title);

        if( empty($this->entity) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($title);
        }

        return $this->setEntity($entity);
    }


    public function getArticles() : array
    {
        if( is_array($this->arrArticles) ) {
            return $this->arrArticles;
        }

        $articleJunctionEntities = $this->entity->getArticles();
        $this->arrArticles = [];
        foreach($articleJunctionEntities as $junctionEntity) {

            $articleEntity  = $junctionEntity->getArticle();
            $articleId      = $articleEntity->getId();
            $this->arrArticles[$articleId] = $this->factory->createArticle($articleEntity);
        }

        return $this->arrArticles;
    }


    public function getFirstArticle() : ?Article
    {
        if( !empty($this->firstArticle) ) {
            return $this->firstArticle;
        }

        $arrArticles        = $this->getArticles();
        $this->firstArticle = reset($arrArticles);
        return $this->firstArticle;
    }


    public function getSpotlightOrDefaultUrlFromArticles(string $size) : string
    {
        $firstArticle = $this->getFirstArticle();
        if( empty($firstArticle) ) {
            return $this->factory->createDefaultSpotlight()->getShortUrl($size);
        }

        return $firstArticle->getSpotlightOrDefaultUrl($size);
    }


    public function getAuthors() : Collection { return $this->entity->getAuthors(); }

    public function getUrl(?int $page = null) : string { return $this->urlGenerator->generateUrl($this, $page); }
}
