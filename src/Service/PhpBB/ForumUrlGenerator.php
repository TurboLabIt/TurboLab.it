<?php
namespace App\Service\PhpBB;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ForumUrlGenerator
{
    public function __construct(protected UrlGeneratorInterface $symfonyUrlGenerator)
    { }


    public function generateHomeUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL)
    {
        $forumHomeUrl = $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . 'forum/';
        return $forumHomeUrl;
    }


    public function generateForumUrlFromId(int $forumId, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL)
    {
        $forumUrl = $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . "forum/viewforum.php?f=$forumId";
        return $forumUrl;
    }


    public function generateTopicViewUrl(Topic $topic, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->generateHomeUrl($urlType) . 'viewtopic.php?t=' . $topic->getId() . "#p" . $topic->getFirstPostId(); }


    public function generateTopicReplyUrl(Topic $topic, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->generateHomeUrl($urlType) . "forum/posting.php?mode=reply&t=" . $topic->getId(); }
}
