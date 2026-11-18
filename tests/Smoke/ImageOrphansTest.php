<?php
namespace App\Tests\Smoke;

use App\Controller\ImageController;
use App\Service\Cms\Image;
use App\Service\Factory;
use App\Service\FrontendHelper;
use App\Service\User;
use App\ServiceCollection\Cms\ImageCollection;
use App\Tests\BaseT;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


/**
 * "Immagini orfane" (/immagini/orfane) shows the images no article uses, which ImagesDelete removes some months later.
 * With orphans, its card has a tile for each: the image, linked to its largest size, its title, its ID and who uploaded
 * it, so that authors can tell theirs; with none, one line says so, as /scarica/da-controllare does for its checks that
 * found nothing (Zane, 2026-11-18).
 *
 * The real controller renders the real template, with orphans the test chooses: the real list changes whenever a test
 * uploads an image or deletes an article.
 */
class ImageOrphansTest extends BaseT
{
    // images every copy of the database has
    const array FIXTURE_IMAGE_IDS = [Image::ID_DEFAULT_SPOTLIGHT, Image::ID_404];


    public function testEachOrphanGetsATile() : void
    {
        $crawler = $this->renderPage(self::FIXTURE_IMAGE_IDS);

        $this->assertSame(['Immagini orfane ' . count(self::FIXTURE_IMAGE_IDS)], $this->texts($crawler, 'main h1'),
            "The title, then how many orphans in its badge");

        $this->assertStringContainsString("Le seguenti immagini non sono utilizzate da alcun articolo",
            $crawler->filter('main')->text(), "The card must say what the images are, and what to do with them");

        $arrExpectedTiles = [];
        foreach(self::FIXTURE_IMAGE_IDS as $imageId) {

            $image = static::getService(Factory::class)->createImage()->load($imageId);
            $authors = implode(', ', array_map(fn(User $author) => $author->getUsername(), $image->getAuthors()));

            $arrExpectedTiles[] = [
                'link'  => $image->getUrl(Image::SIZE_MAX),
                'src'   => $image->getUrl(Image::SIZE_REG),
                'alt'   => $image->getTitle(),
                'title' => $image->getTitle(),
                'meta'  => "#$imageId" . ( $authors === '' ? '' : " · $authors" )
            ];
        }

        $arrTiles =
            $crawler->filter('main .tli-check-images > li')->each( fn(Crawler $tile) => [
                'link'  => $tile->filter('a')->attr('href'),
                'src'   => $tile->filter('img')->attr('src'),
                'alt'   => $tile->filter('img')->attr('alt'),
                'title' => trim( $tile->filter('.tli-check-image-title')->text() ),
                'meta'  => trim( $tile->filter('.tli-check-image-meta')->text() )
            ]);

        $this->assertSame($arrExpectedTiles, $arrTiles,
            "A tile for each orphan, in the order of the list: the image (not the largest size: a page of them would " .
            "weigh megabytes) linked to its largest size, its title, its ID and its authors");

        $this->assertCount(0, $crawler->filter('main .tli-check-passed'), "With orphans, nothing may say there are none");
        $this->assertResourcesForAuthors($crawler);
    }


    public function testNoOrphansIsOneLine() : void
    {
        $crawler = $this->renderPage([]);

        $this->assertSame(['Immagini orfane'], $this->texts($crawler, 'main h1'), "No badge: there is nothing to count");

        $this->assertSame(['Zero "Immagini orfane" - ogni immagine è utilizzata almeno in un articolo'],
            $this->texts($crawler, 'main .tli-check-passed li'),
            "With no orphans, one line says so, in the words /scarica/da-controllare uses for its checks");

        $this->assertCount(0, $crawler->filter('main .tli-check-images'), "No grid without images");
        $this->assertStringNotContainsString("Le seguenti immagini", $crawler->filter('main')->text(),
            "No explanation of a list that isn't there");

        $this->assertResourcesForAuthors($crawler);
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 the page, rendered with the orphans the test chooses ***">
    private function renderPage(array $arrImageIds) : Crawler
    {
        static::loginAsSystem();

        $orphans = new class(static::getService(Factory::class), $arrImageIds) extends ImageCollection {

            public function __construct(Factory $factory, private readonly array $arrImageIds)
            {
                parent::__construct($factory);
            }

            public function loadOrphans() : static
            {
                if( empty($this->arrImageIds) ) {
                    return $this->setEntities([]);
                }

                // in the order given, as the real list keeps its own (oldest first)
                $arrEntities = [];
                foreach($this->load($this->arrImageIds) as $image) {
                    $arrEntities[ $image->getId() ] = $image->getEntity();
                }

                return $this->setEntities( array_map(fn(int $imageId) => $arrEntities[$imageId], $this->arrImageIds) );
            }
        };

        static::getService('request_stack')->push( Request::create( $this->generateUrl('app_image_orphans') ) );

        $response =
            static::getService(ImageController::class)
                ->orphans( $orphans, static::getService(FrontendHelper::class) );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        return new Crawler( $response->getContent() );
    }


    private function assertResourcesForAuthors(Crawler $crawler) : void
    {
        $this->assertSame(['Altre risorse per gli autori'], $this->texts($crawler, 'main h2'));
        $this->assertCount(1, $crawler->filter('main a[href="' . $this->generateUrl('app_file_need-fixing', [], UrlGeneratorInterface::ABSOLUTE_PATH) . '"]'),
            "The resources for authors must link the files to check");
    }


    /** the text of each element, with the non-breaking space that keeps a dash beside its words as a plain space */
    private function texts(Crawler $crawler, string $selector) : array
    {
        return $crawler->filter($selector)->each(
            fn(Crawler $node) => trim( str_replace(mb_chr(0xA0), ' ', $node->text()) )
        );
    }
    //</editor-fold>
}
