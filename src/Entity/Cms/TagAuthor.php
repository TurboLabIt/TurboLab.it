<?php
namespace App\Entity\Cms;

use App\Entity\PhpBB\User;
use App\Repository\Cms\TagAuthorRepository;
use App\Trait\RankableEntityTrait;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: TagAuthorRepository::class)]
#[ORM\UniqueConstraint(name: 'same_tag_same_author_unique_idx', columns: ['tag_id', 'user_id'])]
class TagAuthor extends BaseCmsEntity
{
    #[ORM\ManyToOne(inversedBy: 'authors')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Tag $tag = null;

    #[ORM\ManyToOne(inversedBy: 'tags')]
    #[ORM\JoinColumn(referencedColumnName: 'user_id', nullable: false)]
    protected ?User $user = null;

    use RankableEntityTrait;


    public function getTag() : ?Tag {  return $this->tag; }

    public function setTag(?Tag $tag) : static
    {
        $this->tag = $tag;
        return $this;
    }


    public function getUser() : ?User { return $this->user; }

    public function setUser(?User $user) : static
    {
        $this->user = $user;
        return $this;
    }
}
