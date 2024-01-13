<?php
namespace App\Entity\Cms;

use App\Entity\PhpBB\Topic;
use App\Interface\ArticleInterface;
use App\Repository\Cms\ArticleRepository;
use App\Trait\AbstractableEntityTrait;
use App\Trait\AdsableEntityTrait;
use App\Trait\ArticleFormatableEntityTrait;
use App\Trait\BodyableEntityTrait;
use App\Trait\PublishableEntityTrait;
use App\Trait\TitleableEntityTrait;
use App\Trait\ViewableEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article extends BaseCmsEntity
{
    use
        AbstractableEntityTrait, AdsableEntityTrait, ArticleFormatableEntityTrait,
        BodyableEntityTrait, PublishableEntityTrait, TitleableEntityTrait,
        ViewableEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'coverForArticles')]
    protected ?Image $coverImage = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn('comments_topic_id', 'topic_id')]
    protected ?Topic $commentsTopic = null;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['ranking' => 'ASC'])]
    protected Collection $authors;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleImage::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['ranking' => 'ASC'])]
    protected Collection $images;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleTag::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $tags;


    public function __construct()
    {
        $this->authors  = new ArrayCollection();
        $this->images   = new ArrayCollection();
        $this->tags     = new ArrayCollection();
    }

    public function getCoverImage(): ?Image
    {
        return $this->coverImage;
    }

    public function setCoverImage(?Image $coverImage): static
    {
        $this->coverImage = $coverImage;
        return $this;
    }

    public function getCommentsTopic(): ?Topic
    {
        return $this->commentsTopic;
    }

    public function setCommentsTopic(?Topic $commentsTopic): static
    {
        $this->commentsTopic = $commentsTopic;
        return $this;
    }

    /**
     * @return Collection<int, ArticleAuthor>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(ArticleAuthor $author): static
    {
        $currentItems = $this->getAuthors();
        foreach($currentItems as $item) {

            if( $item->getUser()->getId() == $author->getUser()->getId() ) {
                return $this;
            }
        }

        $this->authors->add($author);
        $author->setArticle($this);

        return $this;
    }

    public function removeAuthor(ArticleAuthor $author): static
    {
        if ($this->authors->removeElement($author)) {
            // set the owning side to null (unless already changed)
            if ($author->getArticle() === $this) {
                $author->setArticle(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection<int, ArticleImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ArticleImage $image): static
    {
        $currentItems = $this->getImages();
        foreach($currentItems as $item) {

            if( $item->getImage()->getId() == $image->getImage()->getId() ) {
                return $this;
            }
        }

        $this->images->add($image);
        $image->setArticle($this);

        return $this;
    }

    public function removeImage(ArticleImage $image): static
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getArticle() === $this) {
                $image->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ArticleTag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(ArticleTag $tag): static
    {
        $currentItems = $this->getTags();
        foreach($currentItems as $item) {

            if( $item->getTag()->getId() == $tag->getTag()->getId() ) {
                return $this;
            }
        }

        $this->tags->add($tag);
        $tag->setArticle($this);

        return $this;
    }

    public function removeTag(ArticleTag $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            // set the owning side to null (unless already changed)
            if ($tag->getArticle() === $this) {
                $tag->setArticle(null);
            }
        }

        return $this;
    }
}
