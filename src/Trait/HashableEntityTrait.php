<?php
namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;


trait HashableEntityTrait
{
    #[ORM\Column(length: 32, unique: true, options: ["fixed" => true])]
    protected ?string $hash = null;


    public function getHash() : ?string { return $this->hash; }


    public function setHash(string $hash) : static
    {
        $this->hash = $hash;
        return $this;
    }
}
