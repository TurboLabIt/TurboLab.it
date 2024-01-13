<?php
namespace App\Entity\Cms;

use App\Entity\User;
use App\Repository\Cms\TagAuthorRepository;
use App\Trait\RankableEntityTrait;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: TagAuthorRepository::class)]
class TagAuthor extends BaseCmsEntity
{
    #[ORM\ManyToOne(inversedBy: 'authors')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Tag $tag = null;

    #[ORM\ManyToOne(inversedBy: 'tags')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?User $user = null;

    use RankableEntityTrait;


    public function getTag(): ?Tag
    {
        return $this->tag;
    }

    public function setTag(?Tag $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
