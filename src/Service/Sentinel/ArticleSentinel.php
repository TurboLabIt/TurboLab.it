<?php
namespace App\Service\Sentinel;

use App\Service\Cms\Article;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


class ArticleSentinel extends BaseSentinel
{
    protected Article $article;


    public function setArticle(Article $article) : static
    {
        $this->article = $article;
        return $this;
    }


    public function canList(?Article $article = null) : bool
    {
        $article = $article ?? $this->article;

        return
            in_array($article->getPublisingStatus(), Article::PUBLISHING_STATUSES_LISTABLE) ||
            $this->canRead($article);
    }


    public function canView(?Article $article = null) : bool
    {
        $article = $article ?? $this->article;

        return
            in_array($article->getPublisingStatus(), Article::PUBLISHING_STATUSES_VISIBLE) ||
            $this->canEdit($article);
    }


    public function canEdit(?Article $article = null) : bool
    {
        $article        = $article ?? $this->article;
        $currentUser    = $this->getCurrentUser();

        if( empty($currentUser) ) {
            return false;
        }

        return $currentUser->isEditor() || $this->isAuthor($article);
    }


    protected function isAuthor(?Article $article = null) : bool
    {
        $article = $article ?? $this->article;
        return array_key_exists($this->getCurrentUser()?->getId() ?? -1, $article->getAuthors());
    }


    public function getAvailablePublishingStatuses(?Article $article = null) : array
    {
        $article        = $article ?? $this->article;
        $currentUser    = $this->getCurrentUser();

        if( $currentUser->isAdmin() ) {
            return Article::PUBLISHING_STATUSES;
        }

        if( $this->canEdit($article) ) {
            return Article::PUBLISHING_STATUSES_AUTHOR_SETTABLE;
        }

        return [];
    }


    public function enforceCanEdit(?Article $article = null, string $errorMessage = "You're not authorized to edit this article") : static
    {
        $article = $article ?? $this->article;

        $this->enforceLoggedUserOnly();

        if( empty( $this->canEdit($article) ) ) {
            throw new AccessDeniedException($errorMessage);
        }

        return $this;
    }


    public function enforceCanSetPublishingStatusTo(mixed $publishingStatus, ?Article $article = null, string $errorMessage = "You're not authorized to set this status on this article") : static
    {
        $this->enforceCanEdit($article);

        if( !in_array($publishingStatus, $this->getAvailablePublishingStatuses($article)) ) {
            throw new AccessDeniedException($errorMessage);
        }

        return $this;
    }
}
