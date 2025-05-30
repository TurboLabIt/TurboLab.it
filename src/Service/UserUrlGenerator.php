<?php
namespace App\Service;

use App\Service\Cms\UrlGenerator;
use App\Service\PhpBB\ForumUrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\Encryptor\Encryptor;


class UserUrlGenerator extends UrlGenerator
{
    public function __construct(
        protected ForumUrlGenerator $forumUrlGenerator, protected UrlGeneratorInterface $symfonyUrlGenerator,
        protected Encryptor $encryptor
    ) {}


    public function generateUrl(User $user, ?int $page = null, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $arrUrlParams = ['usernameClean' => $user->getUsernameClean()];

        if( !empty($page) && $page > 1 ) {
            $arrUrlParams["page"] = $page;
        }

        return $this->symfonyUrlGenerator->generate('app_author', $arrUrlParams, $urlType);
    }


    public function generateForumProfileUrl(User $user, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : ?string
    {
        $userId = $user->getId();
        return $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . "forum/memberlist.php?mode=viewprofile&u=$userId";
    }


    public function generateNewsletterUnsubscribeUrl(User $user, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return
            $this->symfonyUrlGenerator->generate('app_newsletter_unsubscribe', [
                "encryptedSubscriberData" => $this->encryptor->encrypt([
                    "userId"    => $user->getId(),
                    "email"     => $user->getEmail(),
                    "scope"     => "newsletterUnsubscribeUrl"
                ])
            ], $urlType);
    }


    public function generateNewsletterSubscribeUrl(User $user, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return
            $this->symfonyUrlGenerator->generate('app_newsletter_subscribe', [
                "encryptedSubscriberData" => $this->encryptor->encrypt([
                    "userId"    => $user->getId(),
                    "email"     => $user->getEmail(),
                    "scope"     => "newsletterSubscribeUrl"
                ])
            ], $urlType);
    }


    public function generateNewsletterOpenerUrl(
        User $user, ?string $redirectToUrl = null, bool $requiresUrlEncode = true, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL
    ) : string
    {
        if( !empty($redirectToUrl) && $requiresUrlEncode ) {
            $redirectToUrl = urlencode($redirectToUrl);
        }

        return
            $this->symfonyUrlGenerator->generate('app_newsletter_opener', [
                "opener" => $this->encryptor->encrypt([
                    "userId"    => $user->getId(),
                    "email"     => $user->getEmail(),
                    "scope"     => "newsletterOpenerUrl"
                ])
            ], $urlType) . "&url=" . $redirectToUrl;
    }


    public function isUrl(string $urlCandidate) : bool
    {
        if( !$this->isInternalUrl($urlCandidate) ) {
            return false;
        }

        $urlPath = $this->removeDomainFromUrl($urlCandidate);
        if( empty($urlPath) ) {
            return false;
        }

        $match = preg_match('/utenti\/[^\/]+\/?$/', $urlPath);
        return (bool)$match;
    }
}
