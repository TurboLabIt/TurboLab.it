<?php
namespace App\Entity\Cms;

use App\Entity\PhpBB\User;
use App\Repository\Cms\VisitRepository;
use App\Trait\IdableEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;


#[ORM\Entity(repositoryClass: VisitRepository::class)]
class Visit
{
    use IdableEntityTrait, TimestampableEntity;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    protected ?Article $article = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    protected ?Tag $tag = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    protected ?File $file = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(referencedColumnName: 'user_id', nullable: true, onDelete: 'CASCADE')]
    protected ?User $user = null;

    #[ORM\Column(length: 45, nullable: true)]
    protected ?string $ipAddress = null;


    public function getArticle() : ?Article { return $this->article; }

    public function setArticle(?Article $article) : static
    {
        $this->article = $article;
        return $this;
    }


    public function getTag() : ?Tag { return $this->tag; }

    public function setTag(?Tag $tag) : static
    {
        $this->tag = $tag;
        return $this;
    }


    public function getFile() : ?File { return $this->file; }

    public function setFile(?File $file) : static
    {
        $this->file = $file;
        return $this;
    }


    public function getUser() : ?User { return $this->user; }

    public function setUser(?User $user) : static
    {
        $this->user = $user;
        return $this;
    }


    public function getIpAddress() : ?string { return $this->ipAddress; }

    public function setIpAddress(?string $ipAddress) : static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }
}
