<?php
namespace App\Trait;

use App\Entity\PhpBB\Topic;
use App\Exception\InvalidEnumException;
use App\Service\Cms\Article;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


trait CommentsTopicableEntityTrait
{
    use CommentsTopicStatusesTrait;


    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn('comments_topic_id', 'topic_id')]
    protected ?Topic $commentsTopic = null;

    #[ORM\Column(type: Types::SMALLINT, options: ["unsigned" => true, "default" => Article::COMMENTS_TOPIC_NEEDS_UPDATE_NO])]
    protected ?int $commentsTopicNeedsUpdate = Article::COMMENTS_TOPIC_NEEDS_UPDATE_NO;


    public function getCommentsTopic() : ?Topic { return $this->commentsTopic; }

    public function setCommentsTopic(?Topic $commentsTopic) : static
    {
        $this->commentsTopic = $commentsTopic;
        return $this;
    }


    public function getCommentsTopicNeedsUpdate() : ?int { return $this->commentsTopicNeedsUpdate; }

    public function setCommentsTopicNeedsUpdate(int $commentsTopicNeedsUpdate) : static
    {
        if( !in_array($commentsTopicNeedsUpdate, static::COMMENTS_TOPIC_NEEDS_UPDATE_STATUSES ) ) {
            throw new InvalidEnumException("Invalid value for the article comments topic update status field");
        }

        $this->commentsTopicNeedsUpdate = $commentsTopicNeedsUpdate;
        return $this;
    }
}
