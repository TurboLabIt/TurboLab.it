<?php
namespace App\Trait;


trait PublishingStatusesTrait
{
    const int PUBLISHING_STATUS_DRAFT               = 0;
    const int PUBLISHING_STATUS_READY_FOR_REVIEW    = 3;
    const int PUBLISHING_STATUS_PUBLISHED           = 5;
    const int PUBLISHING_STATUS_REJECTED            = 7;
    const int PUBLISHING_STATUS_REMOVED             = 9;
}
