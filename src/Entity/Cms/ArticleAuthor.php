<?php
namespace App\Entity\Cms;

use App\Entity\PhpBB\User;
use App\Repository\Cms\ArticleAuthorRepository;
use App\Trait\RankableEntityTrait;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArticleAuthorRepository::class)]
#[ORM\UniqueConstraint(name: 'same_article_same_author_unique_idx', columns: ['article_id', 'user_id'])]
class ArticleAuthor extends BaseCmsEntity
{
    #[ORM\ManyToOne(inversedBy: 'authors')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Article $article = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(referencedColumnName: 'user_id', nullable: false)]
    protected ?User $user = null;

    use RankableEntityTrait;


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
