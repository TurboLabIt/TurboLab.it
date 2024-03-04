<?php
namespace App\Entity\PhpBB;

use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleFile;
use App\Entity\Cms\ArticleTag;
use App\Entity\Cms\FileAuthor;
use App\Entity\Cms\ImageAuthor;
use App\Entity\Cms\TagAuthor;
use App\Exception\InvalidIdException;
use App\Repository\PhpBB\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/tree/main/docs/users.md
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: "turbolab_it_forum.phpbb_users")]
class User implements UserInterface
{
    // 👀 https://turbolab.it/forum/memberlist.php?mode=viewprofile&u=5103
    const int SYSTEM_USER_ID = 5103;

    // 👀 https://turbolab.it/forum/memberlist.php?mode=viewprofile&u=4015
    const int TESTER_USER_ID = 4015;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    protected ?int $user_id = null;

    #[ORM\Column(unique: true)]
    protected ?string $username = null;

    #[ORM\Column(unique: true)]
    protected ?string $user_email = null;

    #[ORM\Column]
    protected ?string $user_avatar_type = null;

    #[ORM\Column]
    protected ?string $user_avatar = null;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    protected ?int $user_posts = 0;

    #[ORM\Column]
    protected ?string $user_colour = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    protected ?int $user_allow_massemail = 1;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    protected ?int $user_type = 0;

    //#[ORM\Column]
    protected array $roles = [];

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ArticleAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $articles;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ImageAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $images;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TagAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $tags;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ArticleTag::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $articlesTagged;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: FileAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $files;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ArticleFile::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    protected Collection $articlesAttachedToFiles;


    public function __construct()
    {
        $this->articles                 = new ArrayCollection();
        $this->images                   = new ArrayCollection();
        $this->tags                     = new ArrayCollection();
        $this->articlesTagged           = new ArrayCollection();
        $this->files                    = new ArrayCollection();
        $this->articlesAttachedToFiles  = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->user_id;
    }

    public function setId(int $id) : static
    {
        if( empty($id) || $id < 1 ) {
            throw new InvalidIdException();
        }

        $this->user_id = $id;
        return $this;
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


    public function getEmail() : ?string
    {
        return $this->user_email;
    }

    public function setEmail(string $email) : static
    {
        $this->user_email = $email;
        return $this;
    }

    public function getAvatarType() : ?string
    {
        return $this->user_avatar_type;
    }

    public function setAvatarType(string $avatarType) : static
    {
        $this->user_avatar_type = $avatarType;
        return $this;
    }

    public function getAvatarFile() : ?string
    {
        return $this->user_avatar;
    }

    public function setAvatarFile(string $avatarFile) : static
    {
        $this->user_avatar = $avatarFile;
        return $this;
    }

    public function getPostNum() : ?int
    {
        return $this->user_posts;
    }

    public function setPostNum(int $postNum) : static
    {
        $this->user_posts = $postNum;
        return $this;
    }

    public function getColor() : ?string
    {
        return $this->user_colour;
    }

    public function setColor(string $color) : static
    {
        $this->user_colour = $color;
        return $this;
    }

    public function getAllowMassEmail() : bool
    {
        return (bool)$this->user_allow_massemail;
    }

    public function setAllowMassEmail(int|bool $allow) : static
    {
        $this->user_allow_massemail = (int)$allow;
        return $this;
    }


    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->username;
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
