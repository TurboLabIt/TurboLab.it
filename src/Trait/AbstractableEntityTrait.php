<?php
namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;


trait AbstractableEntityTrait
{
    #[ORM\Column(length: 2000, nullable: true)]
    protected ?string $abstract = null;

    public function getAbstract(): ?string { return $this->abstract; }

    public function setAbstract(?string $abstract): static
    {
        $this->abstract = $abstract;
        return $this;
    }
}
