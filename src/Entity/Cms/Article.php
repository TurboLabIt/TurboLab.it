<?php
namespace App\Entity\Cms;

use App\Repository\Cms\ArticleRepository;
use App\Service\Cms\Tag as TagService;
use App\Service\Newsletter;
use App\Trait\AbstractableEntityTrait;
use App\Trait\AdsableEntityTrait;
use App\Trait\ArticleFormatsTrait;
use App\Trait\BodyableEntityTrait;
use App\Trait\CommentsTopicableEntityTrait;
use App\Trait\PublishableEntityTrait;
use App\Trait\TitleableEntityTrait;
use App\Trait\ViewableEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Index(name: 'title_fulltext_idx', columns: ['title'], flags: ['fulltext'])]
class Article extends BaseCmsEntity
{
    const string TLI_CLASS = 'article';

    use
        AbstractableEntityTrait, AdsableEntityTrait, ArticleFormatsTrait,
        BodyableEntityTrait, PublishableEntityTrait, TitleableEntityTrait,
        ViewableEntityTrait, CommentsTopicableEntityTrait;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Choice(choices: [self::FORMAT_ARTICLE, self::FORMAT_NEWS], message: 'Invalid Article format')]
    protected ?int $format = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $archived = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $excludedFromPeriodicUpdateList = false;

    #[ORM\ManyToOne(inversedBy: 'spotlightForArticles')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?Image $spotlight = null;

    #[ORM\OneToMany(targetEntity: ArticleAuthor::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ranking' => 'ASC'])]
    protected Collection $authors;

    #[ORM\OneToMany(targetEntity: ArticleImage::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ranking' => 'ASC'])]
    protected Collection $images;

    #[ORM\OneToMany(targetEntity: ArticleTag::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $tags;

    #[ORM\OneToMany(targetEntity: ArticleFile::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $files;

    #[ORM\OneToMany(targetEntity: ArticleGroup::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $articleGroups;

    #[ORM\OneToMany(targetEntity: ArticleBadge::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $badges;


    public function __construct()
    {
        $this->authors          = new ArrayCollection();
        $this->images           = new ArrayCollection();
        $this->tags             = new ArrayCollection();
        $this->files            = new ArrayCollection();
        $this->articleGroups    = new ArrayCollection();
        $this->badges           = new ArrayCollection();
    }


    #[Groups(['searchable'])]
    public function getFormat() : ?int { return $this->format; }


    #[Groups(['searchable'])]
    public function getAuthorIds() : array
    {
        $ids = [];
        foreach($this->authors as $articleAuthor) {
            $ids[] = $articleAuthor->getUser()->getId();
        }
        return $ids;
    }


    #[Groups(['searchable'])]
    public function getPublishedAtTimestamp() : ?int
    {
        return $this->publishedAt?->getTimestamp();
    }


    public function setFormat(int $format) : static
    {
        static::validateFormat($format);
        $this->format = $format;
        return $this;
    }


    public function isArchived() : bool { return $this->archived; }

    public function setArchived(bool $archived = true) : static
    {
        $this->archived = $archived;
        return $this;
    }


    public function isExcludedFromPeriodicUpdateList() : bool { return $this->excludedFromPeriodicUpdateList; }

    public function excludeFromPeriodicUpdateList(bool $exclude = true) : static
    {
        $this->excludedFromPeriodicUpdateList = $exclude;
        return $this;
    }


    public function getSpotlight() : ?Image { return $this->spotlight; }

    public function setSpotlight(?Image $spotlight) : static
    {
        $this->spotlight = $spotlight;
        return $this;
    }


    public function isNewsletter() : bool { return stripos($this->getTitle(), Newsletter::TITLE) !== false; }


    public function isIndexable() : bool
    {
        if(
            !in_array($this->getPublishingStatus(), static::PUBLISHING_STATUSES_INDEXABLE) ||
            $this->isNewsletter()
        ) {
            return false;
        }

        $tags = $this->getTags();
        foreach($tags as $junction) {

            if(
                $junction->getTag()->getId() == TagService::ID_SPONSOR &&
                $this->getFormat() == static::FORMAT_NEWS
            ) {
                return false;
            }
        }

        return true;
    }


    /**
     * @return Collection<int, ArticleAuthor>
     */
    public function getAuthors() : Collection { return $this->authors; }

    public function addAuthor(ArticleAuthor $author) : static
    {
        $ranking = 0;
        $currentItems = $this->getAuthors();
        foreach($currentItems as $item) {

            if( $item->getUser()->getId() == $author->getUser()->getId() ) {
                return $this;
            }

            $itemRanking    = $item->getRanking();
            $ranking        = max($itemRanking, $ranking);
        }

        $author
            ->setArticle($this)
            ->setRanking(++$ranking);

        $this->authors->add($author);

        return $this;
    }

    public function removeAuthor(ArticleAuthor $author) : static
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
    public function getImages() : Collection { return $this->images; }

    public function addImage(ArticleImage $image) : static
    {
        $ranking = 0;
        $currentItems = $this->getImages();
        foreach($currentItems as $item) {

            if( $item->getImage()->getId() == $image->getImage()->getId() ) {
                return $this;
            }

            $itemRanking    = $item->getRanking();
            $ranking        = max($itemRanking, $ranking);
        }

        $image
            ->setArticle($this)
            ->setRanking(++$ranking);

        $this->images->add($image);

        return $this;
    }

    public function removeImage(ArticleImage $image) : static
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
    public function getTags() : Collection { return $this->tags; }

    public function addTag(ArticleTag $tag) : static
    {
        $ranking = 0;
        $currentItems = $this->getTags();
        foreach($currentItems as $item) {

            if( $item->getTag()->getId() == $tag->getTag()->getId() ) {
                return $this;
            }

            $itemRanking    = $item->getRanking();
            $ranking        = max($itemRanking, $ranking);
        }

        $tag
            ->setArticle($this)
            ->setRanking(++$ranking);

        $this->tags->add($tag);

        return $this;
    }

    public function removeTag(ArticleTag $tag) : static
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
    public function getFiles() : Collection
    {
        $items = $this->files->toArray();

        usort($items, function(ArticleFile $a, ArticleFile $b) : int {

            $rank = $a->getRanking() <=> $b->getRanking();
            if( $rank !== 0 ) {
                return $rank;
            }

            return $b->getFile()?->getUpdatedAt() <=> $a->getFile()?->getUpdatedAt();
        });

        return new ArrayCollection($items);
    }

    public function addFile(ArticleFile $file) : static
    {
        foreach($this->files as $item) {

            if( $item->getFile()->getId() == $file->getFile()->getId() ) {
                return $this;
            }
        }

        $this->files->add($file);
        $file->setArticle($this);

        return $this;
    }

    public function removeFile(ArticleFile $file) : static
    {
        $currentItems = $this->getFiles();
        foreach($currentItems as $item) {

            if( $item->getFile()->getId() == $file->getFile()->getId() ) {

                $this->files->removeElement($item);
                $item->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ArticleGroup>
     */
    public function getArticleGroups() : Collection { return $this->articleGroups; }

    public function addArticleGroup(ArticleGroup $articleGroup): static
    {
        if (!$this->articleGroups->contains($articleGroup)) {
            $this->articleGroups->add($articleGroup);
            $articleGroup->setArticle($this);
        }

        return $this;
    }

    public function removeArticleGroup(ArticleGroup $articleGroup): static
    {
        if ($this->articleGroups->removeElement($articleGroup)) {
            // set the owning side to null (unless already changed)
            if ($articleGroup->getArticle() === $this) {
                $articleGroup->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ArticleBadge>
     */
    public function getBadges() : Collection { return $this->badges; }

    public function addBadge(ArticleBadge $badge) : static
    {
        if (!$this->badges->contains($badge)) {
            $this->badges->add($badge);
            $badge->setArticle($this);
        }

        return $this;
    }

    public function removeBadge(ArticleBadge $badge) : static
    {
        if ($this->badges->removeElement($badge)) {
            // set the owning side to null (unless already changed)
            if ($badge->getArticle() === $this) {
                $badge->setArticle(null);
            }
        }

        return $this;
    }
}
