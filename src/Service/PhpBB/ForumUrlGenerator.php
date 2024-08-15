<?php
namespace App\Service\PhpBB;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ForumUrlGenerator
{
    const string AJAX_LOADING_PATH = 'ajax/commenti/';

    public function __construct(protected UrlGeneratorInterface $symfonyUrlGenerator) {}

    public function generateHomeUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . 'forum/'; }

    public function generateForumUrlFromId(int $forumId, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) :string
        { return $this->generateHomeUrl($urlType) . "viewforum.php?f=$forumId"; }

    public function generateTopicViewUrl(Topic $topic, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->generateHomeUrl($urlType) . 'viewtopic.php?t=' . $topic->getId() . "#p" . $topic->getFirstPostId(); }

    public function generateTopicReplyUrl(Topic $topic, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->generateHomeUrl($urlType) . "forum/posting.php?mode=reply&t=" . $topic->getId(); }

    public function generateCommentsAjaxLoadingUrl(Topic $topic, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . static::AJAX_LOADING_PATH . $topic->getId(); }
}
