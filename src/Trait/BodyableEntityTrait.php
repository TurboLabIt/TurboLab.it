<?php
namespace App\Trait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


trait BodyableEntityTrait
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $body = null;

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;
        return $this;
    }
}
