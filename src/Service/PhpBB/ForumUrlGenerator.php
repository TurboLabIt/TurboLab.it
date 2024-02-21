<?php
namespace App\Service\PhpBB;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ForumUrlGenerator
{
    public function __construct(protected UrlGeneratorInterface $symfonyUrlGenerator)
    { }


    public function generateHomeUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL)
    {
        $websiteHomeUrl = $this->symfonyUrlGenerator->generate('app_home', [], $urlType);
        $websiteHomeUrl = trim($websiteHomeUrl, '/') . '/';
        $forumHomeUrl   = $websiteHomeUrl . 'forum/';
        return $forumHomeUrl;
    }


    public function generateTopicViewUrl(Topic $topic, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $url = $this->generateHomeUrl($urlType) . 'viewtopic.php?t=' . $topic->getId();
        return $url;
    }
}
