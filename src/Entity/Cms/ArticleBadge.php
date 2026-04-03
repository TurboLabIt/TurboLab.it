<?php
namespace App\Entity\Cms;

use App\Repository\Cms\ArticleBadgeRepository;
use App\Trait\ArticleRelationableEntityTrait;
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

    use ArticleRelationableEntityTrait;


    public function getBadge() : ?Badge { return $this->badge; }

    public function setBadge(?Badge $badge) : static
    {
        $this->badge = $badge;
        return $this;
    }
}
