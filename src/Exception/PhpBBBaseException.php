<?php
namespace App\Exception;

use App\Service\PhpBB\Topic;


abstract class PhpBBBaseException extends \RuntimeException
{
    protected ?Topic $topic = null;

    public function getTopic() : ?Topic { return $this->topic; }

    public function setTopic(Topic $topic) : static
    {
        $this->topic = $topic;
        return $this;
    }
}
