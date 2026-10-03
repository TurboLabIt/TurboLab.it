<?php
namespace App\Tests\Editor;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * The title of an uploaded image starts as its file name, so Image::getTitle() drops the extension.
 * It used to do so with rtrim($title, ".$format") for each format: rtrim() takes a list of
 * CHARACTERS, not a suffix, so every trailing letter found in ".avif.webp.png.jpg" was chopped off
 * too — "iPhone" became "iPho", "Google" became "Googl", "logo-big.png" became "logo-bi" — and the
 * mangled title spread to the alt text, the editor gallery and the image URL slug (7% of the images).
 *
 * @see \App\Service\Cms\Image::getTitle()
 */
class ImageTitleTest extends BaseT
{
    public static function titleProvider() : array
    {
        return [
            // words ending in one of the letters of ".avif.webp.png.jpg" stay whole
            'iPhone'                    => ['iPhone',                   'iPhone'],
            'Google'                    => ['Google',                   'Google'],
            'Microsoft Edge'            => ['Microsoft Edge',           'Microsoft Edge'],
            'logo-big.png'              => ['logo-big.png',             'logo-big'],
            'screenshot-wifi.png'       => ['screenshot-wifi.png',      'screenshot-wifi'],

            // the extension goes, whatever its case and spelling
            'jpg'                       => ['foto.jpg',                 'foto'],
            'jpeg'                      => ['Paw Catcher ai.jpeg',      'Paw Catcher ai'],
            'webp'                      => ['config-wifi.webp',         'config-wifi'],
            'avif'                      => ['archivio.avif',            'archivio'],
            'gif'                       => ['animazione.gif',           'animazione'],
            'svg converted to png'      => ['Tux.svg',                  'Tux'],
            'uppercase'                 => ['foto.PNG',                 'foto'],
            'stacked extensions'        => ['2h4kidf.jpg.png',          '2h4kidf'],
            'space before extension'    => ['screenshot .png',          'screenshot'],

            // ...but nothing else
            'no extension'              => ['babbo-natale-high-tech',   'babbo-natale-high-tech'],
            'dot inside the name'       => ['T-TurboLab.it',            'T-TurboLab.it'],
            'version number'            => ['Firefox 128.0.png',        'Firefox 128.0'],
            'not an image extension'    => ['nginx.conf',               'nginx.conf'],
            'format in the middle'      => ['png-vs-jpg confronto',     'png-vs-jpg confronto'],

            // nothing left: fallback
            'extension only'            => ['.png',                     'image'],
        ];
    }


    #[DataProvider('titleProvider')]
    public function testTitleDropsOnlyTheFileExtension(string $storedTitle, string $expectedTitle) : void
    {
        $image =
            static::getService(Factory::class)
                ->createImage( (new ImageEntity())->setTitle($storedTitle) );

        $this->assertSame($expectedTitle, $image->getTitle());
    }


    public function testSlugIsBuiltFromTheWholeTitle() : void
    {
        $factory = static::getService(Factory::class);

        foreach(['iPhone' => 'iphone', 'screenshot-wifi.png' => 'screenshot-wifi'] as $storedTitle => $expectedSlug) {

            $image = $factory->createImage( (new ImageEntity())->setTitle($storedTitle) );
            $this->assertSame($expectedSlug, $image->getSlug());
        }
    }
}
