<?php
namespace App\Service\Cms;

use App\Service\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class TagUrlGenerator extends UrlGenerator
{
    public function generateUrl(Tag $tag, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $tagSlugDashId = $this->buildSlugDashIdString($tag);

        $tagUrl =
            $this->symfonyUrlGenerator->generate('app_tag', [
                "tagSlugDashId" => $tagSlugDashId
            ], $urlType);

        return $tagUrl;
    }


    public function generateShortUrl(Tag $tag, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $tagShortUrl =
            $this->symfonyUrlGenerator->generate('app_tag_shorturl', [
                "id" => $tag->getId()
            ], $urlType);

        return $tagShortUrl;
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

        $match = preg_match('/^\/[^\/]+-[1-9]+[0-9]*$/', $urlPath);
        return (bool)$match;
    }
}