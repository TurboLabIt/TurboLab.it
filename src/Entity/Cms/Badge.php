<?php
namespace App\Entity\Cms;

use App\Repository\Cms\BadgeRepository;
use App\Trait\AbstractableEntityTrait;
use App\Trait\BodyableEntityTrait;
use App\Trait\TitleableEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: BadgeRepository::class)]
class Badge extends BaseCmsEntity
{
    const string TLI_CLASS = 'badge';

    use TitleableEntityTrait, AbstractableEntityTrait, BodyableEntityTrait;

    #[ORM\Column(length: 1024, nullable: true)]
    protected ?string $imageUrl = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $userSelectable = false;

    #[ORM\OneToMany(mappedBy: 'badge', targetEntity: TagBadge::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $tags;

    #[ORM\OneToMany(targetEntity: ArticleBadge::class, mappedBy: 'badge', orphanRemoval: true)]
    private Collection $articles;


    public function __construct()
    {
        $this->tags     = new ArrayCollection();
        $this->articles = new ArrayCollection();
    }


    public function getImageUrl() : ?string { return $this->imageUrl; }

    public function setImageUrl(?string $imageUrl) : static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }


    public function isUserSelectable() : bool { return $this->userSelectable; }

    public function setUserSelectable(bool $userSelectable) : static
    {
        $this->userSelectable = $userSelectable;
        return $this;
    }


    /**
     * @return Collection<int, TagBadge>
     */
    public function getTags() : Collection { return $this->tags; }

    public function addTag(TagBadge $tag) : static
    {
        $currentItems = $this->getTags();
        foreach($currentItems as $item) {

            if( $item->getTag()->getId() == $tag->getTag()->getId() ) {
                return $this;
            }
        }

        $this->tags->add($tag);
        $tag->setBadge($this);

        return $this;
    }

    public function removeTag(TagBadge $tag) : static
    {
        if ($this->tags->removeElement($tag)) {
            // set the owning side to null (unless already changed)
            if ($tag->getBadge() === $this) {
                $tag->setBadge(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ArticleBadge>
     */
    public function getArticles() : Collection { return $this->articles; }

    public function addArticle(ArticleBadge $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setBadge($this);
        }

        return $this;
    }

    public function removeArticle(ArticleBadge $article) : static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getBadge() === $this) {
                $article->setBadge(null);
            }
        }

        return $this;
    }
}
