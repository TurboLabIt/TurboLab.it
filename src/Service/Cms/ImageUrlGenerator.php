<?php
namespace App\Service\Cms;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ImageUrlGenerator extends UrlGenerator
{
    public function generateUrl(Image $image, string $size, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return
            $this->symfonyUrlGenerator->generate('app_image', [
                "size"              => $size,
                "imageFolderMod"    => $image->getFolderMod(),
                "slugDashId"        => $image->getSlug() . "-" . $image->getId(),
                "format"            => Image::getClientSupportedBestFormat(),
            ], $urlType);
    }


    public function generateShortUrl(Image $image, string $size = Image::SIZE_MAX, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return
            $this->symfonyUrlGenerator->generate('app_image_shorturl', [
                "id"    => $image->getId(),
                "size"  => $size
            ], $urlType);
    }
}
