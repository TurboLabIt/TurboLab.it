<?php
namespace App\Entity\Cms;

use App\Repository\Cms\FileRepository;
use App\Trait\TitleableEntityTrait;
use App\Trait\ViewableEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: FileRepository::class)]
class File extends BaseCmsEntity
{
    use TitleableEntityTrait, ViewableEntityTrait;

    #[ORM\Column(length: 15, nullable: true)]
    protected ?string $format = null;

    #[ORM\Column(length: 2500, nullable: true)]
    protected ?string $url = null;

    #[ORM\OneToMany(mappedBy: 'file', targetEntity: FileAuthor::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ranking' => 'ASC'])]
    protected Collection $authors;

    #[ORM\OneToMany(mappedBy: 'file', targetEntity: ArticleFile::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    protected Collection $articles;


    public function __construct()
    {
        $this->authors  = new ArrayCollection();
        $this->articles = new ArrayCollection();
    }


    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): static
    {
        $this->format = $format;
        return $this;
    }


    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }


    /**
     * @return Collection<int, FileAuthor>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(FileAuthor $author): static
    {
        $currentItems = $this->getAuthors();
        foreach($currentItems as $item) {

            if( $item->getUser()->getId() == $author->getUser()->getId() ) {
                return $this;
            }
        }

        $this->authors->add($author);
        $author->setFile($this);

        return $this;
    }

    public function removeAuthor(FileAuthor $author): static
    {
        if ($this->authors->removeElement($author)) {
            // set the owning side to null (unless already changed)
            if ($author->getFile() === $this) {
                $author->setFile(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection<int, ArticleFile>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(ArticleFile $article): static
    {
        $currentItems = $this->getArticles();
        foreach($currentItems as $item) {

            if( $item->getArticle()->getId() == $article->getArticle()->getId() ) {
                return $this;
            }
        }

        $this->articles->add($article);
        $article->setFile($this);

        return $this;
    }

    public function removeArticle(ArticleFile $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getFile() === $this) {
                $article->setFile(null);
            }
        }

        return $this;
    }
}
