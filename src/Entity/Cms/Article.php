<?php
namespace App\Entity\Cms;

use App\Repository\Cms\ArticleRepository;
use App\Trait\AbstractableEntityTrait;
use App\Trait\AdsableEntityTrait;
use App\Trait\ArticleFormatableEntityTrait;
use App\Trait\BodyableEntityTrait;
use App\Trait\CommentTopicableEntityTrait;
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
        ViewableEntityTrait, CommentTopicableEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'spotlightForArticles')]
    protected ?Image $spotlight = null;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleAuthor::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ranking' => 'ASC'])]
    protected Collection $authors;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ranking' => 'ASC'])]
    protected Collection $images;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleTag::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $tags;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleFile::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $files;


    public function __construct()
    {
        $this->authors  = new ArrayCollection();
        $this->images   = new ArrayCollection();
        $this->tags     = new ArrayCollection();
        $this->files    = new ArrayCollection();
    }

    public function getSpotlight(): ?Image
    {
        return $this->spotlight;
    }

    public function setSpotlight(?Image $spotlight): static
    {
        $this->spotlight = $spotlight;
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

    /**
     * @return Collection<int, ArticleFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(ArticleFile $file): static
    {
        $currentItems = $this->getFiles();
        foreach($currentItems as $item) {

            if( $item->getFile()->getId() == $file->getFile()->getId() ) {
                return $this;
            }
        }

        $this->files->add($file);
        $file->setArticle($this);

        return $this;
    }

    public function removeFile(ArticleFile $file): static
    {
        if ($this->files->removeElement($file)) {
            // set the owning side to null (unless already changed)
            if ($file->getArticle() === $this) {
                $file->setArticle(null);
            }
        }

        return $this;
    }
}
