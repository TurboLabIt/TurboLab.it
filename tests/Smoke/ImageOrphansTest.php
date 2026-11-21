<?php
namespace App\Tests\Smoke;

use App\Controller\ImageController;
use App\Repository\Cms\ImageRepository;
use App\Service\Cms\Image;
use App\Service\Factory;
use App\Service\FrontendHelper;
use App\Service\User;
use App\ServiceCollection\Cms\ImageCollection;
use App\Tests\BaseT;
use DateTimeImmutable;
use IntlDateFormatter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


/**
 * "Immagini orfane" (/immagini/orfane) shows the images no article uses, which ImagesDelete removes a year after they
 * lost their last article.
 * With orphans, its card has a tile for each: the image, linked to its largest size, its title, its ID, who uploaded it
 * (linked to their page), so that authors can tell theirs, and when ImagesDelete will remove it; with none, one line says
 * so, as /scarica/da-controllare does for its checks that found nothing (Zane, 2026-11-18).
 *
 * The real controller renders the real template, with orphans the test chooses: the real list changes whenever a test
 * uploads an image or deletes an article.
 */
class ImageOrphansTest extends BaseT
{
    // images every copy of the database has
    const array FIXTURE_IMAGE_IDS = [Image::ID_DEFAULT_SPOTLIGHT, Image::ID_404, Image::ID_CHRISTMAS_SPOTLIGHT];


    public function testEachOrphanGetsATile() : void
    {
        $crawler = $this->renderPage(self::FIXTURE_IMAGE_IDS);

        $this->assertSame(['Immagini orfane ' . count(self::FIXTURE_IMAGE_IDS)], $this->texts($crawler, 'main h1'),
            "The title, then how many orphans in its badge");

        $explanation = $crawler->filter('main .tli-check > p')->first()->text();
        $this->assertStringContainsString("Le seguenti immagini non sono utilizzate da alcun articolo", $explanation,
            "The card must say what the images are, and what to do with them");

        $this->assertSame(12, ImageRepository::DELETE_ORPHANS_AFTER_MONTHS,
            "The card says «un anno»: change its words with the constant");
        $this->assertStringContainsString(
            "saranno cancellate automaticamente un anno dopo essere rimaste senza articoli, alla data indicata sotto ognuna",
            $explanation, "The card must say when the images go"
        );

        $arrExpectedTiles = [];
        foreach(self::FIXTURE_IMAGE_IDS as $imageId) {

            $image      = static::getService(Factory::class)->createImage()->load($imageId);
            $arrAuthors = $image->getAuthors();
            $authors    = implode(', ', array_map(fn(User $author) => $author->getUsername(), $arrAuthors));

            $arrExpectedTiles[] = [
                'link'          => $image->getUrl(Image::SIZE_MAX),
                'src'           => $image->getUrl(Image::SIZE_REG),
                'alt'           => $image->getTitle(),
                'title'         => $image->getTitle(),
                'meta'          => "#$imageId" . ( $authors === '' ? '' : " · $authors" ),
                'authorLinks'   => array_values( array_map(
                    fn(User $author) => $this->generateUrl('app_author',
                        ['usernameClean' => $author->getUsernameClean()], UrlGeneratorInterface::ABSOLUTE_PATH),
                    $arrAuthors
                ))
            ];
        }

        $arrTiles =
            $crawler->filter('main .tli-check-images > li')->each( fn(Crawler $tile) => [
                'link'          => $tile->filter('a')->attr('href'),
                'src'           => $tile->filter('img')->attr('src'),
                'alt'           => $tile->filter('img')->attr('alt'),
                'title'         => trim( $tile->filter('.tli-check-image-title')->text() ),
                'meta'          => trim( $tile->filter('.tli-check-image-meta')->text() ),
                'authorLinks'   => $tile->filter('.tli-check-image-meta a')->each( fn(Crawler $link) => $link->attr('href') )
            ]);

        $this->assertSame($arrExpectedTiles, $arrTiles,
            "A tile for each orphan, in the order of the list: the image (not the largest size: a page of them would " .
            "weigh megabytes) linked to its largest size, its title, its ID and its authors, each linked to their " .
            "page by a relative address");

        $this->assertCount(0, $crawler->filter('main .tli-check-passed'), "With orphans, nothing may say there are none");
        $this->assertResourcesForAuthors($crawler);
    }


    /**
     * ImagesDelete removes an orphan DELETE_ORPHANS_AFTER_MONTHS after it lost its last article (orphanedAt): the tile
     * says when, in red from the last 30 days on, and "a breve" once the date has passed (the next run is at most 3
     * hours away). The dates are the test's: the images' last change, years older, must not count
     */
    public function testEachTileSaysWhenItsImageWillBeDeleted() : void
    {
        $months = ImageRepository::DELETE_ORPHANS_AFTER_MONTHS;

        $arrOrphanedAt = [
            // goes in months
            Image::ID_DEFAULT_SPOTLIGHT     => new DateTimeImmutable('-1 month'),
            // goes in 10 days
            Image::ID_404                   => (new DateTimeImmutable('+10 days'))->modify("-$months months"),
            // due: the next run deletes it
            Image::ID_CHRISTMAS_SPOTLIGHT   => (new DateTimeImmutable('-1 hour'))->modify("-$months months"),
        ];

        $crawler = $this->renderPage( array_keys($arrOrphanedAt), $arrOrphanedAt );

        $deletionDate = fn(DateTimeImmutable $orphanedAt) => $this->formatDate( $orphanedAt->modify("+$months months") );

        $this->assertSame([
            ['text' => 'Cancellazione: ' . $deletionDate($arrOrphanedAt[Image::ID_DEFAULT_SPOTLIGHT]), 'soon' => false],
            ['text' => 'Cancellazione: ' . $deletionDate($arrOrphanedAt[Image::ID_404]), 'soon' => true],
            ['text' => 'Cancellazione: a breve', 'soon' => true],
        ],
            $this->deletionLines($crawler),
            "Each tile must say when its image goes, $months months after it lost its last article, in red from the " .
            "last 30 days on"
        );
    }


    /**
     * An image that lost its last article since the last ImagesDelete run has no orphanedAt yet: the next run dates it,
     * so its months start now
     */
    public function testAnOrphanNotDatedYetGetsItsWholeYear() : void
    {
        $months   = ImageRepository::DELETE_ORPHANS_AFTER_MONTHS;
        $expected = fn() => ['text' => 'Cancellazione: ' . $this->formatDate( new DateTimeImmutable("+$months months") ), 'soon' => false];

        // the date before and after: the page may be built across midnight
        $arrExpected    = [ $expected() ];
        $crawler        = $this->renderPage([Image::ID_DEFAULT_SPOTLIGHT], [Image::ID_DEFAULT_SPOTLIGHT => null]);
        $arrExpected[]  = $expected();

        $arrLines = $this->deletionLines($crawler);
        $this->assertCount(1, $arrLines);
        $this->assertContains($arrLines[0], $arrExpected, "An orphan not dated yet must go $months months from now");
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
    /**
     * @param int[] $arrImageIds the orphans, in this order
     * @param array<int, ?DateTimeImmutable> $arrOrphanedAt image ID => when the test says it lost its last article (null: not
     *                                             dated yet); default: its own
     */
    private function renderPage(array $arrImageIds, array $arrOrphanedAt = []) : Crawler
    {
        static::loginAsSystem();

        $orphans = new class(static::getService(Factory::class), $arrImageIds, $arrOrphanedAt) extends ImageCollection {

            public function __construct(
                Factory $factory, private readonly array $arrImageIds, private readonly array $arrOrphanedAt
            )
            {
                parent::__construct($factory);
            }

            public function loadOrphans() : static
            {
                if( empty($this->arrImageIds) ) {
                    return $this->setEntities([]);
                }

                // in the order given, as the real list keeps its own (oldest first); copies, so that a date the test
                // changes is never written back to the database
                $arrEntities = [];
                foreach($this->load($this->arrImageIds) as $image) {

                    $entity = clone $image->getEntity();
                    if( array_key_exists($image->getId(), $this->arrOrphanedAt) ) {
                        $entity->setOrphanedAt( $this->arrOrphanedAt[ $image->getId() ] );
                    }

                    $arrEntities[ $image->getId() ] = $entity;
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


    /** each tile's deletion line, and whether it is red */
    private function deletionLines(Crawler $crawler) : array
    {
        return
            $crawler->filter('main .tli-check-images > li .tli-check-image-deletion')->each( fn(Crawler $line) => [
                'text' => trim( $line->text() ),
                'soon' => str_contains( (string)$line->attr('class'), 'tli-check-image-deletion-soon' )
            ]);
    }


    /** as the page writes a date: 4 dic 2026 */
    private function formatDate(DateTimeImmutable $date) : string
    {
        return
            (new IntlDateFormatter('it', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'd MMM y'))
                ->format($date);
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
