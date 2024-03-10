<?php
namespace App\Service\Cms;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class TagUrlGenerator extends UrlGenerator
{
    public function generateUrl(Tag $tag, ?int $page = null, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $arrUrlParams = ['tagSlugDashId' => $this->buildSlugDashIdString($tag)];

        if( !empty($page) && $page > 1 ) {
            $arrUrlParams["page"] = $page;
        }

        $tagUrl = $this->symfonyUrlGenerator->generate('app_tag', $arrUrlParams, $urlType);
        return $tagUrl;
    }


    public function isUrl(string $urlCandidate) : bool
    {
        return !empty( $this->extractIdFromUrl($urlCandidate) );
    }


    public function extractIdFromUrl(string $url) : ?int
    {
        if( !$this->isInternalUrl($url) ) {
            return null;
        }

        $url = $this->removeDomainFromUrl($url);

        $arrMatches = [];
        $match = preg_match('/^\/[^\/]+-([1-9]+[0-9]*)$/', $url, $arrMatches);
        if( $match === 1 ) {

            $id = end($arrMatches);
            return (int)$id;
        }

        return null;
    }
}
