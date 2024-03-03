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
    )
    {}


    public function generateUrl(User $user, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $userUrl =
            $this->symfonyUrlGenerator->generate('app_user', [
                "usernameClean" => $user->getUsernameClean()
            ], $urlType);

        return $userUrl;
    }


    public function generateForumProfileUrl(User $user, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : ?string
    {
        $userId = $user->getId();
        $url    = $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . "memberlist.php?mode=viewprofile&u=$userId";
        return $url;
    }


    public function generateNewsletterUnsubscribeUrl(User $user, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $unsubscribeUrl =
            $this->symfonyUrlGenerator->generate('app_newsletter_unsubscribe', [
                "encryptedSubscriberData" => $this->encryptor->encrypt([
                    "userId"    => $user->getId(),
                    "email"     => $user->getEmail()
                ])
            ], $urlType);

        return $unsubscribeUrl;
    }


    public function generateNewsletterSubscribeUrl(User $user, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $subscribeUrl =
            $this->symfonyUrlGenerator->generate('app_newsletter_subscribe', [
                "encryptedSubscriberData" => $this->encryptor->encrypt([
                    "userId"    => $user->getId(),
                    "email"     => $user->getEmail()
                ])
            ], $urlType);

        return $subscribeUrl;
    }


    public function generateNewsletterOpenerUrl(
        User $user, ?string $redirectToUrl = null, bool $requiresUrlEncode = true, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL
    ) : string
    {
        if( !empty($redirectToUrl) && $requiresUrlEncode ) {
            $redirectToUrl = urlencode($redirectToUrl);
        }

        $subscribeUrl =
            $this->symfonyUrlGenerator->generate('app_newsletter_opener', [
                "opener" => $this->encryptor->encrypt([
                    "userId"    => $user->getId(),
                    "email"     => $user->getEmail()
                ])
            ], $urlType) . "&url=" . $redirectToUrl;

        return $subscribeUrl;
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

        $match = preg_match('/utenti/^\/[^\/]+$/', $urlPath);
        return (bool)$match;
    }
}
