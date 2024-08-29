<?php
namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;


trait ViewableEntityTrait
{
    #[ORM\Column(options: ['unsigned' => true])]
    protected int $views = 0;

    public function getViews(): ?int { return $this->views; }

    public function setViews(int $views): static
    {
        $this->views = $views;
        return $this;
    }
}
