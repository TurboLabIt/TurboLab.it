<?php
namespace App\Entity\PhpBB;

use App\Entity\Cms\Article;
use App\Repository\PhpBB\TopicRepository;
use App\Trait\ViewableEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: TopicRepository::class)]
#[ORM\Table(name: "turbolab_it_forum.phpbb_topics")]
class Topic
{
    use ViewableEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "topic_id", options: ['unsigned' => true])]
    protected ?int $id = null;

    #[ORM\Column(name: "topic_title", length: 512)]
    protected ?string $title = null;

    #[ORM\Column(name: "topic_posts_approved", options: ['unsigned' => true])]
    protected ?int $postNum = null;

    #[ORM\Column(options: ['unsigned' => true])]
    protected ?int $forumId = null;

    #[ORM\Column(name: "topic_last_post_time", options: ['unsigned' => true])]
    protected ?int $lastPostTime = null;

    #[ORM\Column(name: "topic_last_post_id", options: ['unsigned' => true])]
    protected ?int $lastPostId = null;

    #[ORM\Column(name: "topic_last_poster_name", length: 512)]
    protected ?string $lastPosterName = null;

    #[ORM\Column(name: "topic_last_poster_colour", length: 10)]
    protected ?string $lastPosterColor = null;

    #[ORM\Column(name: "topic_time", options: ['unsigned' => true])]
    protected ?int $time = null;

    #[ORM\Column(name: "topic_visibility", options: ['unsigned' => true])]
    protected ?int $visibility = null;

    #[ORM\Column(name: "topic_delete_time", options: ['unsigned' => true])]
    protected ?int $deleteTime = null;

    #[ORM\Column(name: "topic_views", options: ['unsigned' => true])]
    protected int $views = 0;

    #[ORM\Column(name: "topic_status", options: ['unsigned' => true])]
    protected int $status = 0;

    #[ORM\OneToMany(mappedBy: 'commentsTopic', targetEntity: Article::class)]
    protected Collection $articles;


    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getPostNum(): ?int
    {
        return $this->postNum;
    }

    public function setPostNum(int $postNum): static
    {
        $this->postNum = $postNum;
        return $this;
    }

    public function getForumId(): ?int
    {
        return $this->forumId;
    }

    public function setForumId(int $forumId): static
    {
        $this->forumId = $forumId;
        return $this;
    }

    public function getLastPostTime(): ?int
    {
        return $this->lastPostTime;
    }

    public function setLastPostTime(int $lastPostTime): static
    {
        $this->lastPostTime = $lastPostTime;
        return $this;
    }

    public function getLastPostId(): ?int
    {
        return $this->lastPostId;
    }

    public function setLastPostId(int $lastPostId): static
    {
        $this->lastPostId = $lastPostId;
        return $this;
    }

    public function getLastPosterName(): ?string
    {
        return $this->lastPosterName;
    }

    public function setLastPosterName(string $lastPosterName): static
    {
        $this->lastPosterName = $lastPosterName;
        return $this;
    }

    public function getLastPosterColor(): ?string
    {
        return $this->lastPosterColor;
    }

    public function setLastPosterColor(string $lastPosterColor): static
    {
        $this->lastPosterColor = $lastPosterColor;
        return $this;
    }

    public function getTime(): ?int
    {
        return $this->time;
    }

    public function setTime(int $time): static
    {
        $this->time = $time;
        return $this;
    }


    public function isvisibility(): ?int
    {
        return $this->visibility;
    }

    public function setvisibility(int $visibility): static
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function getDeleteTime(): ?int
    {
        return $this->deleteTime;
    }

    public function setDeleteTime(int $deleteTime): static
    {
        $this->deleteTime = $deleteTime;
        return $this;
    }


    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;
        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setCommentsTopic($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getCommentsTopic() === $this) {
                $article->setCommentsTopic(null);
            }
        }

        return $this;
    }
}
