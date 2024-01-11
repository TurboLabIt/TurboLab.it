<?php
namespace App\Entity\Cms;

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
    use AbstractableEntityTrait;
    use AdsableEntityTrait;
    use ArticleFormatableEntityTrait;
    use BodyableEntityTrait;
    use PublishableEntityTrait;
    use TitleableEntityTrait;
    use ViewableEntityTrait;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleAuthor::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['ranking' => 'ASC'])]
    protected Collection $authors;


    public function __construct()
    {
        $this->authors = new ArrayCollection();
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
}
