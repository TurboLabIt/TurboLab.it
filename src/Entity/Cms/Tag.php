<?php
namespace App\Entity\Cms;

use App\Repository\Cms\TagRepository;
use App\Trait\AbstractableEntityTrait;
use App\Trait\RankableEntityTrait;
use App\Trait\TitleableEntityTrait;
use App\Trait\ViewableEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag extends BaseCmsEntity
{
    use TitleableEntityTrait, AbstractableEntityTrait, RankableEntityTrait, ViewableEntityTrait;

    #[ORM\ManyToOne(targetEntity: self::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?self $replacement = null;

    #[ORM\OneToMany(mappedBy: 'tag', targetEntity: TagAuthor::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ranking' => 'ASC'])]
    protected Collection $authors;

    #[ORM\OneToMany(mappedBy: 'tag', targetEntity: ArticleTag::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    protected Collection $articles;

    #[ORM\OneToMany(mappedBy: 'tag', targetEntity: TagBadge::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $badges;


    public function __construct()
    {
        $this->authors  = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->badges   = new ArrayCollection();
    }


    public function getReplacement() : ?Tag { return $this->replacement; }

    public function setReplacement(?Tag $replacement) : static
    {
        $this->replacement = $replacement;
        return $this;
    }


    /**
     * @return Collection<int, TagAuthor>
     */
    public function getAuthors() : Collection { return $this->authors; }

    public function addAuthor(TagAuthor $author) : static
    {
        $ranking = 0;
        $currentItems = $this->getAuthors();
        foreach($currentItems as $item) {

            if( $item->getUser()->getId() == $author->getUser()->getId() ) {
                return $this;
            }

            $itemRanking    = $item->getRanking();
            $ranking        = $itemRanking > $ranking ? $itemRanking : $ranking;
        }

        $author
            ->setTag($this)
            ->setRanking(++$ranking);

        $this->authors->add($author);

        return $this;
    }

    public function removeAuthor(TagAuthor $author) : static
    {
        if ($this->authors->removeElement($author)) {
            // set the owning side to null (unless already changed)
            if ($author->getTag() === $this) {
                $author->setTag(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection<int, ArticleTag>
     */
    public function getArticles() : Collection { return $this->articles; }

    public function addArticle(ArticleTag $article) : static
    {
        $currentItems = $this->getArticles();
        foreach($currentItems as $item) {

            if( $item->getArticle()->getId() == $article->getArticle()->getId() ) {
                return $this;
            }
        }

        $this->articles->add($article);
        $article->setTag($this);

        return $this;
    }

    public function removeArticle(ArticleTag $article) : static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getTag() === $this) {
                $article->setTag(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TagBadge>
     */
    public function getBadges() : Collection { return $this->badges; }

    public function addBadge(TagBadge $badge) : static
    {
        if (!$this->badges->contains($badge)) {
            $this->badges->add($badge);
            $badge->setTag($this);
        }

        return $this;
    }

    public function removeBadge(TagBadge $badge) : static
    {
        if ($this->badges->removeElement($badge)) {
            // set the owning side to null (unless already changed)
            if ($badge->getTag() === $this) {
                $badge->setTag(null);
            }
        }

        return $this;
    }
}
