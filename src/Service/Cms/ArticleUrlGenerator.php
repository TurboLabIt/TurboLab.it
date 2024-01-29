<?php
namespace App\Service\Cms;

use App\Service\UrlGenerator;
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
        $topicId = $article->getEntity()?->getCommentsTopic()?->getId();
        if( empty($topicId) ) {
            return null;
        }

        $url = $this->symfonyUrlGenerator->generate('app_home', [], $urlType) . "forum/viewtopic.php?t=$topicId";
        return $url;
    }
}
