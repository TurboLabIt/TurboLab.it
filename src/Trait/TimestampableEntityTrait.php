<?php
namespace App\Trait;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * Gedmo's TimestampableEntity with immutable dates: the same two columns (created_at, updated_at, NOT NULL), set by
 * the same listener, but Gedmo's own trait maps them as DateTime, a Doctrine type deprecated since DBAL 4.6
 * (https://github.com/doctrine/dbal/pull/7575) and gone in DBAL 5. tests/Convention/ImmutableDatesTest.php keeps
 * every date immutable
 */
trait TimestampableEntityTrait
{
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?DateTimeImmutable $updatedAt = null;


    public function getCreatedAt() : ?DateTimeImmutable { return $this->createdAt; }

    public function setCreatedAt(DateTimeImmutable $createdAt) : static
    {
        $this->createdAt = $createdAt;
        return $this;
    }


    public function getUpdatedAt() : ?DateTimeImmutable { return $this->updatedAt; }

    public function setUpdatedAt(DateTimeImmutable $updatedAt) : static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
