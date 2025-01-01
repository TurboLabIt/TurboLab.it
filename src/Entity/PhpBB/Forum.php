<?php
namespace App\Entity\PhpBB;

use App\Entity\BaseEntity;
use App\Repository\PhpBB\ForumRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ForumRepository::class)]
// this entity maps a table from the phpBB database.
// the mapping is handled by https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Doctrine/TLINamingStrategy.php
class Forum extends BaseEntity
{
    /**
     *  4 : area staff
     * 25 : cestinate
     *  7 : area prove
     */
    const array OFFLIMITS_FORUM_IDS = [4,25,7];
    const int COMMENTS_FORUM_ID     = 26;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "forum_id", options: ['unsigned' => true])]
    protected ?int $id = null;

    #[ORM\Column(options: ['unsigned' => true])]
    protected ?int $parentId = null;

    #[ORM\Column(name: "forum_name", length: 512)]
    protected ?string $name = null;

    #[ORM\Column(name: "forum_type", type: Types::SMALLINT)]
    protected ?int $type = null;

    #[ORM\Column(name: "forum_status", type: Types::SMALLINT)]
    protected ?int $status = null;

    #[ORM\Column(name: "forum_last_post_time", options: ['unsigned' => true])]
    protected ?int $last_post_time = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): static
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getLastPostTime(): ?int
    {
        return $this->last_post_time;
    }

    public function setLastPostTime(int $last_post_time): static
    {
        $this->last_post_time = $last_post_time;
        return $this;
    }
}
