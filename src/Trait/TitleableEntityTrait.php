<?php
namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;


trait TitleableEntityTrait
{
    #[ORM\Column(length: 250)]
    protected ?string $title = null;

    public function getTitle() : ?string
    {
        return $this->title;
    }

    public function setTitle(string $title) : static
    {
        $this->title = $title;
        return $this;
    }
}
