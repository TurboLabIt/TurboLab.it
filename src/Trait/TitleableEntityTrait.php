<?php
namespace App\Trait;

use App\Exception\EmptyParameterException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\NotBlank;


trait TitleableEntityTrait
{
    #[ORM\Column(length: 512)]
    #[NotBlank]
    protected ?string $title = null;

    public function getTitle() : ?string { return $this->title; }

    public function setTitle(string $title) : static
    {
        $title = trim($title);

        if( empty($title) ) {
            throw new EmptyParameterException('The article title cannot be empty');
        }

        $this->title = $title;
        return $this;
    }
}
