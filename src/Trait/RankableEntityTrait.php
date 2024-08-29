<?php
namespace App\Trait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


trait RankableEntityTrait
{
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    protected ?int $ranking = 1;

    public function getRanking(): ?int { return $this->ranking; }

    public function setRanking(int $ranking): static
    {
        $this->ranking = $ranking;
        return $this;
    }
}
