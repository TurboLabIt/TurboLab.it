<?php
namespace App\Entity\Cms;

use App\Repository\Cms\ArticleImageRepository;
use App\Trait\RankableEntityTrait;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArticleImageRepository::class)]
#[ORM\UniqueConstraint(name: 'same_article_same_image_unique_idx', columns: ['article_id', 'image_id'])]
class ArticleImage extends BaseCmsEntity
{
    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Article $article = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Image $image = null;

    use RankableEntityTrait;


    public function getArticle() : ?Article { return $this->article; }

    public function setArticle(?Article $article) : static
    {
        $this->article = $article;
        return $this;
    }


    public function getImage() : ?Image { return $this->image; }

    public function setImage(?Image $image) : static
    {
        $this->image = $image;
        return $this;
    }
}
