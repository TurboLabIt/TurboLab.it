<?php
namespace App\Service\Sentinel;

use App\Service\Cms\File;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


class FileSentinel extends BaseSentinel
{
    protected File $file;


    public function setFile(File $file) : static
    {
        $this->file = $file;
        return $this;
    }


    public function canView(?File $file = null) : bool
    {
        $file = $file ?? $this->file;
        return $file->isVisitable() || $this->canEdit($file) || $this->canEditAnyArticle($file);
    }


    public function canEdit(?File $file = null) : bool
    {
        $file           = $file ?? $this->file;
        $currentUser    = $this->getCurrentUser();

        if( empty($currentUser?->getId()) ) {
            return false;
        }

        return $currentUser->isEditor() || $this->isAuthor($file);
    }


    protected function isAuthor(?File $file = null) : bool
    {
        $file = $file ?? $this->file;
        return array_key_exists($this->getCurrentUser()?->getId() ?? -1, $file->getAuthors());
    }


    /**
     * canEdit() only knows about the authors of the *file*, so a co-author who didn't upload it would be
     * locked out of the very draft they are writing. Whoever can edit an article the file hangs on can
     * download it too.
     */
    protected function canEditAnyArticle(?File $file = null) : bool
    {
        $file = $file ?? $this->file;

        foreach($file->getArticles() as $article) {

            if( $this->factory->createArticleSentinel($article)->canEdit() ) {
                return true;
            }
        }

        return false;
    }


    public function enforceCanView(?File $file = null, string $errorMessage = "You're not authorized to download this file") : static
    {
        $file = $file ?? $this->file;

        if( empty( $this->canView($file) ) ) {
            throw new AccessDeniedException($errorMessage);
        }

        return $this;
    }


    public function enforceCanEdit(?File $file = null, string $errorMessage = "You're not authorized to edit this file") : static
    {
        $file = $file ?? $this->file;

        if( empty( $this->canEdit($file) ) ) {
            throw new AccessDeniedException($errorMessage);
        }

        return $this;
    }
}
