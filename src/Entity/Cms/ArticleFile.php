<?php
namespace App\Entity\Cms;

use App\Entity\PhpBB\User;
use App\Repository\Cms\ArticleFileRepository;
use App\Trait\RankableEntityTrait;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArticleFileRepository::class)]
#[ORM\UniqueConstraint(name: 'same_article_same_file_unique_idx', columns: ['article_id', 'file_id'])]
class ArticleFile extends BaseCmsEntity
{
    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    protected ?Article $article = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    protected ?File $file = null;

    #[ORM\ManyToOne(inversedBy: 'articlesAttachedToFiles')]
    #[ORM\JoinColumn(referencedColumnName: 'user_id', nullable: false)]
    protected ?User $user = null;

    use RankableEntityTrait;


    public function getArticle() : ?Article { return $this->article; }

    public function setArticle(?Article $article) : static
    {
        $this->article = $article;
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
}
