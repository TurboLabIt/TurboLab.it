<?php
namespace App\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class FrontendHelper
{
    public function __construct(protected UrlGeneratorInterface $urlGenerator) {}


    public function getOwnFollowUsPages() : array
    {
        return [
            $this->buildLink(
                "Forum e newsletter", '/forum/ucp.php?mode=register', false,
                '/images/social-icons/email.svg', 'fa-solid fa-user-group'
            ),
            $this->buildLink(
                "Feed RSS", $this->urlGenerator->generate('app_feed'), false,
                '/images/social-icons/rss.svg', 'fa fa-square-rss'
            ),
        ];
    }


    public function getSocialMediaPages() : array
    {
        return [
            $this->buildLink("YouTube", 'https://www.youtube.com/c/turbolabit?sub_confirmation=1'),
            $this->buildLink("Telegram", 'https://t.me/turbolab'),
            $this->buildLink("Facebook", 'https://www.facebook.com/TurboLab.it'),
            $this->buildLink(
                "X (Twitter)", 'https://twitter.com/TurboLabIt', true,
                '/images/social-icons/x-twitter.svg', 'fab fa-x-twitter'
            ),
            $this->buildLink("GitHub", 'https://github.com/TurboLabIt/'),
            $this->buildLink("LinkedIn", 'https://linkedin.com/company/turbolabit')
        ];
    }


    protected function buildLink(
        string $name, string $url, bool $blank = true,
        ?string $iconFileName = null, ?string $faIcon = null
    ) : array
    {
        return [
            'name'  => $name,
            'icon'  => $iconFileName ?? ( '/images/social-icons/' . mb_strtolower($name) . '.svg' ),
            "fa"    => $faIcon ?? ( 'fab fa-' . mb_strtolower($name) ),
            "url"   => $url,
            "blank" => $blank
        ];
    }
}
