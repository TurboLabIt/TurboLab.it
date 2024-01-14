<?php
namespace App\Entity\Cms;

use App\Entity\User;
use App\Repository\Cms\FileAuthorRepository;
use App\Trait\RankableEntityTrait;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: FileAuthorRepository::class)]
class FileAuthor extends BaseCmsEntity
{
    #[ORM\ManyToOne(inversedBy: 'authors')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?File $file = null;

    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?User $user = null;

    use RankableEntityTrait;


    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
