<?php
namespace App\Trait;


trait PublishingStatusesTrait
{
    const int PUBLISHING_STATUS_DRAFT               = 0;
    const int PUBLISHING_STATUS_READY_FOR_REVIEW    = 3;
    const int PUBLISHING_STATUS_PUBLISHED           = 5;
    const int PUBLISHING_STATUS_KO                  = 7;
    const array PUBLISHING_STATUSES                 = [0, 3, 5, 7];

    const int PUBLISHING_ACTION_PUBLISH_URGENTLY    = 55;

    const array PUBLISHING_STATUSES_AUTHOR_SETTABLE = [0, 3];
    const array PUBLISHING_STATUSES_LISTABLE        = [0, 3, 5];
    const array PUBLISHING_STATUSES_INDEXABLE       = [0, 3, 5];
    const array PUBLISHING_STATUSES_VISIBLE         = [3, 5];
}
