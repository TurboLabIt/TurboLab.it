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
    use TitleableEntityTrait, AbstractableEntityTrait, BodyableEntityTrait;

    #[ORM\Column(length: 1024, nullable: true)]
    protected ?string $imageUrl = null;

    #[ORM\Column]
    protected bool $userSelectable = true;

    #[ORM\OneToMany(mappedBy: 'badge', targetEntity: TagBadge::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $tags;


    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function isUserSelectable(): ?bool
    {
        return $this->userSelectable;
    }

    public function setUserSelectable(bool $userSelectable): static
    {
        $this->userSelectable = $userSelectable;
        return $this;
    }

    /**
     * @return Collection<int, TagBadge>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(TagBadge $tag): static
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

    public function removeTag(TagBadge $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            // set the owning side to null (unless already changed)
            if ($tag->getBadge() === $this) {
                $tag->setBadge(null);
            }
        }

        return $this;
    }
}
