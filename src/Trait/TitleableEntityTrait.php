<?php
namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Validator\Constraints\NotBlank;


trait TitleableEntityTrait
{
    const int TITLE_MAX_LENGTH = 512;

    #[ORM\Column(length: self::TITLE_MAX_LENGTH, unique: true)]
    #[NotBlank]
    protected ?string $title = null;

    public function getTitle() : ?string { return $this->title; }


    public function getTitleComparable() : string
    {
        $title = $this->getTitle() ?: '';
        $processed = mb_strtolower($title);
        $processed = preg_replace('/[^a-z0-9]/i', '', $processed);
        return trim($processed);
    }


    public function setTitle(string $title) : static
    {
        $title = trim($title);

        if( empty($title) ) {
            throw new BadRequestException('The article title cannot be empty');
        }

        $this->title = $title;
        return $this;
    }
}
