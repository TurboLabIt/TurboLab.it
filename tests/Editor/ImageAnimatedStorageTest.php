<?php
namespace App\Tests\Editor;

use App\Service\Cms\ArticleEditor;
use App\Service\Cms\Image;
use App\Service\Factory;
use App\Tests\BaseT;


/**
 * An animated GIF/WebP is the only image whose display URL is always .webp, whatever the client supports.
 * CKEditor loads the body as displayed, so on save that URL must turn back into the image placeholder,
 * or the GIF is lost on the first edit. Nothing is persisted: setBody() alone runs the storage processing.
 *
 * @see \App\Service\Cms\Image::getUrl()
 * @see \App\Service\HtmlProcessorForStorage
 */
class ImageAnimatedStorageTest extends BaseT
{
    // the quality-test article ends with an animated GIF
    public function testQualityTestArticleRoundTripKeepsEveryImage() : void
    {
        // the body links files, and setBody() attaches them in the name of the current user
        static::loginAsSystem();

        $article = static::getArticle();

        $arrImages = $article->getImagesInUse();
        $arrAnimated = array_filter($arrImages, fn(Image $image) => $image->canBeBuiltAnimated());
        $this->assertNotEmpty($arrAnimated, 'The quality-test article has no animated image');

        $stored = $this->storeBody( $article->getBodyForDisplay() );

        foreach($arrImages as $image) {
            $this->assertStringContainsString('src="==###immagine::id::' . $image->getId() . '###=="', $stored);
        }
    }


    public function testEverySizeOfAnAnimatedUrlBecomesAPlaceholder() : void
    {
        $arrAnimated = array_filter(static::getArticle()->getImagesInUse(), fn(Image $image) => $image->canBeBuiltAnimated());
        $image = reset($arrAnimated);
        $this->assertInstanceOf(Image::class, $image);

        foreach([Image::SIZE_MIN, Image::SIZE_MED, Image::SIZE_REG, Image::SIZE_MAX] as $size) {

            $url = $image->getUrl($size);
            $this->assertStringEndsWith('.webp', (string)parse_url($url, PHP_URL_PATH));

            $stored = $this->storeBody('<p><img src="' . $url . '"></p>');
            $this->assertStringContainsString('src="==###immagine::id::' . $image->getId() . '###=="', $stored, "Size $size");
            $this->assertStringNotContainsString('.webp', $stored, "Size $size stored as a raw URL");
        }
    }


    protected function storeBody(string $body) : string
    {
        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle('Animated image storage probe ' . bin2hex(random_bytes(4)))
            ->setBody($body);

        return (string)$editor->getEntity()->getBody();
    }
}
