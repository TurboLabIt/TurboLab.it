<?php
namespace App\Entity;

use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleFile;
use App\Entity\Cms\ArticleTag;
use App\Entity\Cms\FileAuthor;
use App\Entity\Cms\ImageAuthor;
use App\Entity\Cms\TagAuthor;
use App\Repository\UserRepository;
use App\Trait\IdableEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;


#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface
{
    use IdableEntityTrait;

    #[ORM\Column(length: 180, unique: true)]
    protected ?string $username = null;

    #[ORM\Column]
    protected array $roles = [];

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ArticleAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $articles;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ImageAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $images;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TagAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $tags;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ArticleTag::class, cascade: ['persist', 'remove'])]
    private Collection $articlesTagged;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: FileAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $files;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ArticleFile::class, cascade: ['persist', 'remove'])]
    private Collection $articlesAttachedToFiles;


    public function __construct()
    {
        $this->articles         = new ArrayCollection();
        $this->images           = new ArrayCollection();
        $this->tags             = new ArrayCollection();
        $this->articlesTagged   = new ArrayCollection();
        $this->files            = new ArrayCollection();
        $this->fileTags         = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }


    /**
     * @return Collection<int, ArticleAuthor>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(ArticleAuthor $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setUser($this);
        }

        return $this;
    }

    public function removeArticle(ArticleAuthor $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getUser() === $this) {
                $article->setUser(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection<int, ImageAuthor>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ImageAuthor $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setUser($this);
        }

        return $this;
    }

    public function removeImage(ImageAuthor $image): static
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getUser() === $this) {
                $image->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TagAuthor>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(TagAuthor $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->setUser($this);
        }

        return $this;
    }

    public function removeTag(TagAuthor $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            // set the owning side to null (unless already changed)
            if ($tag->getUser() === $this) {
                $tag->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ArticleTag>
     */
    public function getArticlesTagged(): Collection
    {
        return $this->articlesTagged;
    }

    public function addArticleTag(ArticleTag $articleTag): static
    {
        if (!$this->articlesTagged->contains($articleTag)) {
            $this->articlesTagged->add($articleTag);
            $articleTag->setUser($this);
        }

        return $this;
    }

    public function removeArticleTag(ArticleTag $articleTag): static
    {
        if ($this->articlesTagged->removeElement($articleTag)) {
            // set the owning side to null (unless already changed)
            if ($articleTag->getUser() === $this) {
                $articleTag->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FileAuthor>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(FileAuthor $file): static
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setUser($this);
        }

        return $this;
    }

    public function removeFile(FileAuthor $file): static
    {
        if ($this->files->removeElement($file)) {
            // set the owning side to null (unless already changed)
            if ($file->getUser() === $this) {
                $file->setUser(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection<int, ArticleFile>
     */
    public function getArticlesAttachedToFiles(): Collection
    {
        return $this->articlesAttachedToFiles;
    }

    public function addArticleFile(ArticleFile $articleFile): static
    {
        if (!$this->articlesAttachedToFiles->contains($articleFile)) {
            $this->articlesAttachedToFiles->add($articleFile);
            $articleFile->setUser($this);
        }

        return $this;
    }

    public function removeArticleFile(ArticleFile $articleFile): static
    {
        if ($this->articlesAttachedToFiles->removeElement($articleFile)) {
            // set the owning side to null (unless already changed)
            if ($articleFile->getUser() === $this) {
                $articleFile->setUser(null);
            }
        }

        return $this;
    }
}
