<?php
namespace App\Trait;

use App\Exception\InvalidEnumException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


trait PublishableEntityTrait
{
    const PUBLISHING_STATUS_DRAFT               = 0;
    const PUBLISHING_STATUS_READY_FOR_REVIEW    = 3;
    const PUBLISHING_STATUS_PUBLISHED           = 5;
    const PUBLISHING_STATUS_REJECTED            = 7;
    const PUBLISHING_STATUS_REMOVED             = 9;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    protected ?int $publishingStatus = 0; // default to PUBLISHING_STATUS_DRAFT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $publishedAt = null;

    public function publishingStatusCountOneView() : bool
    {
        $result =
            $this->publishingStatus > static::PUBLISHING_STATUS_DRAFT &&
            $this->publishingStatus <= static::PUBLISHING_STATUS_PUBLISHED;

        return $result;
    }

    public function getStatuses() : array
    {
        return [
            static::PUBLISHING_STATUS_DRAFT, static::PUBLISHING_STATUS_READY_FOR_REVIEW,
            static::PUBLISHING_STATUS_PUBLISHED, static::PUBLISHING_STATUS_REJECTED,
            static::PUBLISHING_STATUS_REMOVED
        ];
    }

    public function getPublishingStatus(): ?int
    {
        return $this->publishingStatus;
    }

    public function setPublishingStatus(int $status): static
    {
        if( !in_array($status, $this->getStatuses()) ) {
            throw new InvalidEnumException("Invalid publishing stauts for the article");
        }

        $this->publishingStatus = $status;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }
}
