<?php
namespace App\Trait;

use App\Exception\InvalidEnumException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


trait ArticleFormatableEntityTrait
{
    const FORMAT_ARTICLE    = 1;
    const FORMAT_NEWS       = 2;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    protected ?int $format = null;

    public function getFormats() : array
    {
        return [static::FORMAT_ARTICLE, static::FORMAT_NEWS];
    }

    public function getFormat(): ?int
    {
        return $this->format;
    }

    public function setFormat(int $format): static
    {
        if( !in_array($format, $this->getFormats()) ) {
            throw new InvalidEnumException("Invalid format for the article");
        }

        $this->format = $format;
        return $this;
    }
}
