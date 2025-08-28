<?php
namespace App\Service\PhpBB;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ForumUrlGenerator
{
    const string AJAX_LOADING_PATH = 'ajax/commenti/';

    public function __construct(protected UrlGeneratorInterface $symfonyUrlGenerator) {}


    public function generateHomeUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . 'forum/';
    }


    public function generateForumUrlFromId(int $forumId, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->generateHomeUrl($urlType) . "viewforum.php?f=$forumId";
    }


    public function generateTopicNewUrlFromForumId(int $forumId, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->generateHomeUrl($urlType) . "posting.php?mode=post&f=$forumId";
    }


    public function generateTopicViewUrl(Topic $topic, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return
            $this->generateHomeUrl($urlType) . 'viewtopic.php?t=' . $topic->getId() . "#p" . $topic->getFirstPostId();
    }


    public function generateTopicReplyUrl(Topic $topic, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->generateHomeUrl($urlType) . "posting.php?mode=reply&t=" . $topic->getId();
    }


    public function generateCommentsAjaxLoadingUrl(Topic $topic, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return
            $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . static::AJAX_LOADING_PATH . $topic->getId();
    }


    public function generateLoginUrl(?string $redirectToUrl = '', int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return
            $this->generateUcpUrl([
                'mode'      => 'login',
                'redirect'  => $redirectToUrl,
            ], $urlType);
    }


    public function generateRegisterUrl(?string $redirectToUrl = '', int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return
            $this->generateUcpUrl([
                'mode'      => 'register',
                // the redirection DOESN'T WORK!
                'redirect'  => $redirectToUrl,
            ], $urlType);
    }


    public function generateForgotPasswordUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->generateHomeUrl($urlType) . 'user/forgot_password';
    }


    public function generateUcpUrl(array $parameters = [], int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $url = $this->generateHomeUrl($urlType). 'ucp.php';

        $parameters = array_filter($parameters);
        if( !empty($parameters) ) {
            $url .= '?' . http_build_query($parameters);
        }

        return $url;
    }
}
