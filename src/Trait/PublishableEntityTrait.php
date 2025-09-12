<?php
namespace App\Trait;

use App\Exception\InvalidEnumException;
use App\Service\Cms\Article;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


trait PublishableEntityTrait
{
    use PublishingStatusesTrait;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => self::PUBLISHING_STATUS_DRAFT])]
    #[Assert\Choice(choices: self::PUBLISHING_STATUSES, message: 'Invalid publishing status')]
    protected int $publishingStatus = Article::PUBLISHING_STATUS_DRAFT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?DateTimeInterface $publishedAt = null;

    public function getPublishingStatus() : int { return $this->publishingStatus; }

    public function setPublishingStatus(int $status) : static
    {
        if( !in_array($status, static::PUBLISHING_STATUSES ) ) {
            throw new InvalidEnumException("Invalid publishing status");
        }

        $this->publishingStatus = $status;
        return $this;
    }


    public function getPublishedAt() : ?DateTimeInterface { return $this->publishedAt; }

    public function setPublishedAt(?DateTimeInterface $publishedAt) : static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }
}
