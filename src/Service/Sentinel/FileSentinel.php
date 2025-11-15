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


    public function enforceCanEdit(?File $file = null, string $errorMessage = "You're not authorized to edit this file") : static
    {
        $file = $file ?? $this->file;

        if( empty( $this->canEdit($file) ) ) {
            throw new AccessDeniedException($errorMessage);
        }

        return $this;
    }
}
