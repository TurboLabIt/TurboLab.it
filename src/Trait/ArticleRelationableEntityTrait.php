<?php
namespace App\Trait;

use App\Entity\Cms\Article;
use App\Entity\PhpBB\User;
use Doctrine\ORM\Mapping as ORM;


trait ArticleRelationableEntityTrait
{
    #[ORM\ManyToOne(inversedBy: 'something')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Article $article = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(referencedColumnName: 'user_id', nullable: false)]
    protected ?User $user = null;


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
