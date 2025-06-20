<?php
namespace App\Trait;


trait PublishingStatusesTrait
{
    const int PUBLISHING_STATUS_DRAFT           = 0;
    const int PUBLISHING_STATUS_READY_FOR_REVIEW= 3;
    const int PUBLISHING_STATUS_PUBLISHED       = 5;
    const int PUBLISHING_STATUS_KO              = 7;

    const array PUBLISHING_STATUSES_OK  = [0, 3, 5];
    const array PUBLISHING_STATUSES     = [0, 3, 5, 7];
}
