<?php
namespace App\Entity\Cms;

use App\Repository\Cms\ArticleGroupRepository;
use App\Trait\RankableEntityTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;


#[ORM\Entity(repositoryClass: ArticleGroupRepository::class)]
#[ORM\UniqueConstraint(name: 'same_article_same_group_ranking_unique_idx', columns: ['group_name', 'article_id', 'ranking'])]
class ArticleGroup extends BaseCmsEntity
{
    #[ORM\Column(length: 50)]
    protected ?string $groupName = null;

    #[ORM\Column(options: ['default' => true])]
    protected bool $visible = true;

    #[ORM\ManyToOne(inversedBy: 'articleGroups')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Article $article = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $notes = null;

    use TimestampableEntity, RankableEntityTrait;


    public function getGroupName() : ?string { return $this->groupName; }

    public function setGroupName(string $groupName) : static
    {
        $this->groupName = $groupName;
        return $this;
    }


    public function isVisible() : bool { return $this->visible; }

    public function setVisible(bool $visible = true) : static
    {
        $this->visible = $visible;
        return $this;
    }


    public function getArticle() : ?Article { return $this->article; }

    public function setArticle(?Article $article) : static
    {
        $this->article = $article;
        return $this;
    }


    public function getNotes() : ?string { return $this->notes;}

    public function setNotes(?string $notes) : static
    {
        $this->notes = $notes;
        return $this;
    }
}

