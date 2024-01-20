<?php
namespace App\Service\Cms;

use App\Service\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ImageUrlGenerator extends UrlGenerator
{
    public function generateUrl(Image $image, string $size, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $imageSlugDashId = $this->buildSlugDashIdString($image);
        $imageUrl =
            $this->symfonyUrlGenerator->generate('app_image', [
                "size"              => $size,
                "imageFolderMod"    => $image->getFolderMod(),
                "imageSlugDashId"   => $imageSlugDashId,
                "format"            => $image->getFormat()
            ], $urlType);

        return $imageUrl;
    }


    public function generateShortUrl(Image $image, string $size, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        $imageShortUrl =
            $this->symfonyUrlGenerator->generate('app_image_shorturl', [
                "id"        => $image->getId(),
                "size"      => $size,
                "format"    => $image->getFormat()
            ], $urlType);

        return $imageShortUrl;
    }
}
