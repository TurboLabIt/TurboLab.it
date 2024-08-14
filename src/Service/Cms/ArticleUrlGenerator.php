<?php
namespace App\Service\Cms;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ArticleUrlGenerator extends UrlGenerator
{
    public function generateUrl(Article $article, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $topTag             = $article->getTopTag();
        $tagSlugDashId      = empty($topTag) ? static::DEFAULT_TAG_SLUG_DASH_ID : $this->buildSlugDashIdString($topTag);

        $articleSlugDashId  = $this->buildSlugDashIdString($article);

        $articleUrl =
            $this->symfonyUrlGenerator->generate('app_article', [
                "tagSlugDashId"     => $tagSlugDashId,
                "articleSlugDashId" => $articleSlugDashId
            ], $urlType);

        return $articleUrl;
    }


    public function generateShortUrl(Article $article, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $articleShortUrl =
            $this->symfonyUrlGenerator->generate('app_article_shorturl', [
                "id" => $article->getId()
            ], $urlType);

        return $articleShortUrl;
    }


    public function generateArticleCommentsUrl(Article $article, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : ?string
    {
        $topic = $article->getEntity()?->getCommentsTopic();

        if( empty($topic) ) {
            return null;
        }

        $topicId        = $topic->getId();
        $firstPostId    = $topic->getFirstPostId();

        $url = $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . "forum/viewtopic.php?t=$topicId#p$firstPostId";
        return $url;
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
