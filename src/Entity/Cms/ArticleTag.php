<?php
namespace App\Entity\Cms;

use App\Entity\PhpBB\User;
use App\Repository\Cms\ArticleTagRepository;
use App\Trait\RankableEntityTrait;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArticleTagRepository::class)]
#[ORM\UniqueConstraint(name: 'same_article_same_tag_unique_idx', columns: ['article_id', 'tag_id'])]
class ArticleTag extends BaseCmsEntity
{
    #[ORM\ManyToOne(inversedBy: 'tags')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Article $article = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Tag $tag = null;

    #[ORM\ManyToOne(inversedBy: 'articlesTagged')]
    #[ORM\JoinColumn(referencedColumnName: 'user_id', nullable: false)]
    protected ?User $user = null;

    use RankableEntityTrait;


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


    public function getUser() : ?User { return $this->user; }

    public function setUser(?User $user) : static
    {
        $this->user = $user;
        return $this;
    }
}
