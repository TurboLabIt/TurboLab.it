<?php
namespace App\Service\Cms;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ImageUrlGenerator extends UrlGenerator
{
    public function generateUrl(Image $image, string $size, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL, ?string $format = null) : string
    {
        $arrParams = [
            "size"              => $size,
            "imageFolderMod"    => $image->getFolderMod(),
            "slugDashId"        => $image->getSlug() . "-" . $image->getId(),
            "format"            => $format ?? Image::getClientSupportedBestFormat(),
        ];

        // Cache-bust only the body-size variant of recently-edited images: the browser caches
        // images for one year, so without this an editor-side change (e.g. watermark position)
        // would stay invisible after a refresh. The buster drops off one year past updatedAt,
        // when any cached copy would have expired anyway.
        $entity     = $image->getEntity();
        $updatedAt  = $entity->getUpdatedAt();
        $createdAt  = $entity->getCreatedAt();

        if(
            $size === Image::SIZE_REG &&
            $updatedAt > $createdAt &&
            $updatedAt > new \DateTime('-1 year')
        ) {
            $arrParams["v"] = $updatedAt->getTimestamp();
        }

        return $this->symfonyUrlGenerator->generate('app_image', $arrParams, $urlType);
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
