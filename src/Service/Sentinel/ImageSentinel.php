<?php
namespace App\Service\Sentinel;

use App\Service\Cms\Image;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


class ImageSentinel extends BaseSentinel
{
    protected Image $image;


    public function setImage(Image $image) : static
    {
        $this->image = $image;
        return $this;
    }


    public function canDelete(?Image $image = null) : bool
    {
        $image          = $image ?? $this->image;
        $currentUser    = $this->getCurrentUser();

        if( empty($currentUser?->getId()) ) {
            return false;
        }

        return $currentUser->isEditor() || $image->isAuthor($this->getCurrentUser());
    }



    public function enforceCanDelete(?Image $image = null, string $errorMessage = "You're not authorized to delete this image") : static
    {
        $image = $image ?? $this->image;

        if( empty( $this->canDelete($image) ) ) {
            throw new AccessDeniedException($errorMessage);
        }

        return $this;
    }
}
