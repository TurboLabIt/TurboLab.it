<?php
namespace App\Service\Cms;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ArticleUrlGenerator extends UrlGenerator
{
    public function generateUrl(Article $article, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $topTag         = $article->getTopTag();
        $tagSlugDashId  = empty($topTag) ? static::DEFAULT_TAG_SLUG_DASH_ID : $this->buildSlugDashIdString($topTag);

        return
            $this->symfonyUrlGenerator->generate('app_article', [
                "tagSlugDashId"     => $tagSlugDashId,
                "articleSlugDashId" => $this->buildSlugDashIdString($article)
            ], $urlType);
    }


    public function generateShortUrl(Article $article, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return
            $this->symfonyUrlGenerator->generate('app_article_shorturl', [
                "id" => $article->getId()
            ], $urlType);
    }


    public function isUrl(string $urlCandidate) : bool { return !empty( $this->extractIdFromUrl($urlCandidate) ); }


    public function extractIdFromUrl(string $url) : ?int
    {
        if( !$this->isInternalUrl($url) ) {
            return null;
        }

        $url = $this->removeDomainFromUrl($url);

        // short URL: https://turbolab.it/1939
        $arrMatches = [];
        $match = preg_match('/^\/[1-9]+[0-9]*$/', $url, $arrMatches);
        if( $match === 1 ) {

            $id = reset($arrMatches);
            return (int)$id;
        }

        // long URL: https://turbolab.it/turbolab.it-1/come-svolgere-test-automatici-turbolab.it-1939
        $arrMatches = [];
        $match = preg_match('/^\/[^\/]+-[1-9]+[0-9]*\/[^\/]+-([1-9]+[0-9]*)$/', $url, $arrMatches);
        if( $match === 1 ) {

            $id = end($arrMatches);
            return (int)$id;
        }

        return null;
    }
}
