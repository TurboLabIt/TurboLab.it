<?php
namespace App\Trait;


trait CommentsTopicStatusesTrait
{
    const int COMMENTS_TOPIC_NEEDS_UPDATE_NO    = 0;
    const int COMMENTS_TOPIC_NEEDS_UPDATE_YES   = 1;
    const int COMMENTS_TOPIC_NEEDS_UPDATE_NEVER = 2;

    const array COMMENTS_TOPIC_NEEDS_UPDATE_STATUSES = [0, 1, 2];
}
