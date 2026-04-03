<?php
namespace App\Entity\Cms;

use App\Entity\PhpBB\User;
use App\Repository\Cms\ArticleBadgeRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArticleBadgeRepository::class)]
#[ORM\UniqueConstraint(name: 'same_article_same_badge_unique_idx', columns: ['article_id', 'badge_id'])]
class ArticleBadge extends BaseCmsEntity
{
    #[ORM\ManyToOne(inversedBy: 'badges')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Article $article = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Badge $badge = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(referencedColumnName: 'user_id', nullable: false)]
    protected ?User $user = null;


    public function getBadge() : ?Badge { return $this->badge; }

    public function setBadge(?Badge $badge) : static
    {
        $this->badge = $badge;
        return $this;
    }


    public function getArticle() : ?Article { return $this->article; }

    public function setArticle(?Article $article) : static
    {
        $this->article = $article;
        return $this;
    }


    public function getUser() : ?User { return $this->user; }

    public function setUser(?User $user) : static
    {
        $this->user = $user;
        return $this;
    }
}
