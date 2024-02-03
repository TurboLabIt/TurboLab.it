<?php
namespace App\Trait;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


trait UrlableServiceTrait
{
    public function getUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->urlGenerator->generateUrl($this, $urlType);
    }

    public function getShortUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->urlGenerator->generateShortUrl($this, $urlType);
    }
}
