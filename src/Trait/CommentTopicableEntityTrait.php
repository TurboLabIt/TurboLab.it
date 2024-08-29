<?php
namespace App\Trait;

use App\Entity\PhpBB\Topic;
use App\Exception\InvalidEnumException;
use Doctrine\ORM\Mapping as ORM;


trait CommentTopicableEntityTrait
{
    use CommentTopicStatusesTrait;


    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn('comments_topic_id', 'topic_id')]
    protected ?Topic $commentsTopic = null;

    //#[ORM\Column(type: Types::SMALLINT, options: ["unsigned" => true])]
    protected ?int $commentTopicNeedsUpdate = 0;

    public function getCommentsTopic() : ?Topic { return $this->commentsTopic; }

    public function setCommentsTopic(?Topic $commentsTopic) : static
    {
        $this->commentsTopic = $commentsTopic;
        return $this;
    }


    public function getCommentTopicNeedsUpdateStatuses() : array
    {
        return [
            static::COMMENT_TOPIC_UPDATE_NO, static::COMMENT_TOPIC_UPDATE_YES,
            static::COMMENT_TOPIC_UPDATE_NEVER
        ];
    }


    public function getCommentTopicNeedsUpdate() : ?int { return $this->commentTopicNeedsUpdate; }

    public function setCommentTopicNeedsUpdate(int $commentTopicNeedsUpdate) : static
    {
        if( !in_array($commentTopicNeedsUpdate, $this->getCommentTopicNeedsUpdateStatuses() ) ) {
            throw new InvalidEnumException("Invalid value for the comment topic update field of the article");
        }

        $this->commentTopicNeedsUpdate = $commentTopicNeedsUpdate;
        return $this;
    }
}
