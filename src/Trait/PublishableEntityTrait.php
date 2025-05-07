<?php
namespace App\Trait;

use App\Exception\InvalidEnumException;
use App\Service\Cms\Article;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


trait PublishableEntityTrait
{
    use PublishingStatusesTrait;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => self::PUBLISHING_STATUS_DRAFT])]
    protected int $publishingStatus = Article::PUBLISHING_STATUS_DRAFT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?DateTimeInterface $publishedAt = null;


    public function publishingStatusCountOneView() : bool
    {
        return
            $this->publishingStatus > static::PUBLISHING_STATUS_DRAFT &&
            $this->publishingStatus <= static::PUBLISHING_STATUS_PUBLISHED;
    }


    public function getStatuses() : array
    {
        return [
            static::PUBLISHING_STATUS_DRAFT, static::PUBLISHING_STATUS_READY_FOR_REVIEW,
            static::PUBLISHING_STATUS_PUBLISHED, static::PUBLISHING_STATUS_REJECTED,
            static::PUBLISHING_STATUS_REMOVED
        ];
    }


    public function getPublishingStatus() : int { return $this->publishingStatus; }

    public function setPublishingStatus(int $status) : static
    {
        if( !in_array($status, $this->getStatuses() ) ) {
            throw new InvalidEnumException("Invalid publishing status for the article");
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
