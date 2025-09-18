<?php
namespace App\Entity;

use App\Entity\PhpBB\Post;
use App\Entity\PhpBB\User;
use App\Repository\BugRepository;
use App\Trait\IdableEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;


#[ORM\Entity(repositoryClass: BugRepository::class)]
class Bug
{
    use IdableEntityTrait, TimestampableEntity;

    #[ORM\ManyToOne(inversedBy: 'bugs')]
    #[ORM\JoinColumn(referencedColumnName: 'user_id', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $userIpAddress = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $remoteId = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $remoteUrl = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(referencedColumnName: 'post_id', nullable: true)]
    private ?Post $post = null;


    public function getUser() : ?User { return $this->user; }

    public function setUser(?User $user) : static
    {
        $this->user = $user;
        return $this;
    }


    public function getUserIpAddress() : ?string { return $this->userIpAddress; }

    public function setUserIpAddress(?string $userIpAddress) : static
    {
        $this->userIpAddress = $userIpAddress;
        return $this;
    }


    public function getRemoteId() : ?string { return $this->remoteId; }

    public function setRemoteId(?string $remoteId) : static
    {
        $this->remoteId = $remoteId;
        return $this;
    }


    public function getRemoteUrl() : ?string { return $this->remoteUrl; }

    public function setRemoteUrl(?string $remoteUrl) : static
    {
        $this->remoteUrl = $remoteUrl;
        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;

        return $this;
    }
}
