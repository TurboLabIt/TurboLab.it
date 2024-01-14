<?php
namespace App\Entity\Cms;

use App\Repository\Cms\TagBadgeRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: TagBadgeRepository::class)]
#[ORM\UniqueConstraint(name: 'same_tag_same_badge_unique_idx', columns: ['tag_id', 'badge_id'])]
class TagBadge extends BaseCmsEntity
{
    #[ORM\ManyToOne(inversedBy: 'badges')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Tag $tag = null;

    #[ORM\ManyToOne(inversedBy: 'tags')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Badge $badge = null;


    public function getTag(): ?Tag
    {
        return $this->tag;
    }

    public function setTag(?Tag $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    public function getBadge(): ?Badge
    {
        return $this->badge;
    }

    public function setBadge(?Badge $badge): static
    {
        $this->badge = $badge;
        return $this;
    }
}
