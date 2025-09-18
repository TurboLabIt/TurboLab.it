<?php
namespace App\Entity\PhpBB;

use App\Entity\BaseEntity;
use App\Repository\PhpBB\PostRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: PostRepository::class)]
// this entity maps a table from the phpBB database.
// the mapping is handled by https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Doctrine/TLINamingStrategy.php
class Post extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "post_id", options: ['unsigned' => true])]
    protected ?int $id = null;

    #[ORM\Column(name: "post_subject", length: 512)]
    protected ?string $title = null;

    #[ORM\Column(options: ['unsigned' => true])]
    protected ?int $topicId = null;

    #[ORM\Column(options: ['unsigned' => true])]
    protected ?int $forumId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "poster_id", referencedColumnName: 'user_id', nullable: true)]
    protected ?User $user = null;

    #[ORM\Column(name: "post_visibility", options: ['unsigned' => true])]
    protected ?int $visibility = null;

    #[ORM\Column(name: "post_delete_time", options: ['unsigned' => true])]
    protected ?int $deleteTime = null;

    #[ORM\Column(name: "post_time", options: ['unsigned' => true])]
    protected ?int $postTime = null;


    public function getId() : ?int { return $this->id; }


    public function getTitle() : ?string { return $this->title; }

    public function setTitle(string $title) : static
    {
        $this->title = $title;
        return $this;
    }


    public function getForumId() : ?int { return $this->forumId; }

    public function setForumId(int $forumId) : static
    {
        $this->forumId = $forumId;
        return $this;
    }


    public function getTopicId() : ?int { return $this->topicId; }

    public function setTopicId(int $topicId) : static
    {
        $this->topicId = $topicId;
        return $this;
    }


    public function getUser() : ?User { return $this->user; }

    public function setUser(?User $user) : static
    {
        $this->user = $user;
        return $this;
    }


    public function getVisibility() : ?int { return $this->visibility; }

    public function setVisibility(int $visibility) : static
    {
        $this->visibility = $visibility;
        return $this;
    }


    public function getPostTime() : ?int { return $this->postTime; }

    public function setPostTime(int $postTime) : static
    {
        $this->postTime = $postTime;
        return $this;
    }
}
