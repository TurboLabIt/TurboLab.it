<?php
namespace App\Tests\ZZ_Commands;

use App\Command\ImagesDeleteCommand;
use App\Entity\Cms\Image as ImageEntity;
use App\Repository\Cms\ImageRepository;
use App\Service\Cms\ArticleEditor;
use App\Service\Cms\ImageEditor;
use App\Service\Factory;
use App\Service\User;
use App\Tests\BaseT;
use DateTime;
use Imagick;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * ImagesDelete removes an orphan DELETE_ORPHANS_AFTER_MONTHS after it lost its last article (image.orphaned_at), at its
 * first run after that. Until 2026-11-19 it counted from the image's last change (updated_at), often years older: such
 * an image went at the first run after it lost its last article, so it showed on /immagini/orfane for a few hours at
 * most, instead of the year the page gives its authors to use it again (Zane, 2026-11-19).
 *
 * Each run first dates the images that lost their last article since the previous one and clears the date of the ones
 * back in an article (ImageRepository::updateOrphanedAt()). The images and articles are the test's own; the runs work
 * on the whole table, as on production.
 */
class ImagesDeleteTest extends BaseT
{
    private const string FIXTURE_TITLE_PREFIX = 'tli-test-images-delete-';

    /** @var array<int, ImageEditor> */
    private array $arrImages = [];

    /** @var array<int, ArticleEditor> */
    private array $arrArticles = [];

    private array $arrTmpFiles = [];


    public function testAnImageIsDatedWhenItLosesItsLastArticleAndUndatedWhenItIsBackInOne() : void
    {
        $image   = $this->uploadImage();
        $article = $this->createArticleWith($image);

        $this->updateOrphanedAt();
        $this->assertNull($this->getOrphanedAt($image), "An image an article uses has no date");

        $this->deleteArticle($article);
        $this->updateOrphanedAt();
        $orphanedAt = $this->getOrphanedAt($image);
        $this->assertNotNull($orphanedAt, "The image lost its last article: the run must date it");
        $this->assertEqualsWithDelta(time(), $orphanedAt->getTimestamp(), 60, "The date must be the run's");

        $this->createArticleWith($image);
        $this->updateOrphanedAt();
        $this->assertNull($this->getOrphanedAt($image), "Back in an article: the run must clear its date");
    }


    public function testAnOrphanGoesAYearAfterItLostItsLastArticleNotAfterItsLastChange() : void
    {
        $months = ImageRepository::DELETE_ORPHANS_AFTER_MONTHS;

        // last changed years ago, an orphan since the previous run: until 2026-11-19, deleted at once
        $orphanedToday  = $this->uploadImage(updatedAt: new DateTime('-3 years'), orphanedAt: new DateTime('-2 hours'));
        // last changed years ago, an orphan since less than a run: not dated yet
        $notDatedYet    = $this->uploadImage(updatedAt: new DateTime('-3 years'));
        // its year is over
        $due            = $this->uploadImage(orphanedAt: new DateTime("-$months months -1 day"));

        // Doctrine clears the ID of an entity it deletes
        [$orphanedTodayId, $notDatedYetId, $dueId] = [$orphanedToday->getId(), $notDatedYet->getId(), $due->getId()];

        $this->runImagesDelete();

        $this->assertTrue($this->imageExists($orphanedTodayId),
            "An image orphaned today must stay for its whole year, however old its last change");
        $this->assertTrue($this->imageExists($notDatedYetId), "An image orphaned in the last 3 hours must stay");
        $this->assertFalse($this->imageExists($dueId), "An orphan whose year is over must go");
        $this->assertNotNull($this->getOrphanedAt($notDatedYet), "The run must date the orphans it finds");
    }


    /** a dry run lists what it would delete, and writes nothing: the command runs once per process, as on cron */
    public function testADryRunDeletesNothingAndDatesNothing() : void
    {
        $months = ImageRepository::DELETE_ORPHANS_AFTER_MONTHS;

        $notDatedYet    = $this->uploadImage();
        $due            = $this->uploadImage(orphanedAt: new DateTime("-$months months -1 day"));

        $output = $this->runImagesDelete(['--dry-run' => true]);

        $this->assertMatchesRegularExpression('/^\|\s*' . $due->getId() . '\s*\|/m', $output,
            "A dry run must list the orphan whose year is over");
        $this->assertTrue($this->imageExists( $due->getId() ), "A dry run must delete nothing");
        $this->assertNull($this->getOrphanedAt($notDatedYet), "A dry run must date nothing");
    }


    public function testTheOrphansAreListedByDeletionDate() : void
    {
        $notDatedYet    = $this->uploadImage();
        $recent         = $this->uploadImage(orphanedAt: new DateTime('-1 month'));
        $oldest         = $this->uploadImage(orphanedAt: new DateTime('-6 months'));

        $arrExpectedIds = [$oldest->getId(), $recent->getId(), $notDatedYet->getId()];
        $arrListedIds   =
            array_map(
                fn(ImageEntity $entity) => $entity->getId(),
                array_values( static::getService(ImageRepository::class)->getOrphans() )
            );

        $this->assertSame($arrExpectedIds, array_values( array_intersect($arrListedIds, $arrExpectedIds) ),
            "The orphans must be listed by deletion date: the oldest date first, the ones not dated yet last");
    }


    protected function tearDown() : void
    {
        // the articles first, and their junctions with them
        foreach($this->arrArticles as $article) {
            $article->delete();
        }

        foreach($this->arrImages as $imageId => $image) {

            // ImagesDelete may have deleted it
            if( $this->imageExists($imageId) ) {
                $image->delete();
            }
        }

        foreach($this->arrTmpFiles as $tmpFile) {
            if( file_exists($tmpFile) ) {
                unlink($tmpFile);
            }
        }

        $this->arrArticles = $this->arrImages = $this->arrTmpFiles = [];
        parent::tearDown();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures ***">
    /** A new image no article uses, with the dates given written straight to the database (Doctrine would set now) */
    private function uploadImage(?DateTime $updatedAt = null, ?DateTime $orphanedAt = null) : ImageEditor
    {
        // plasma is random: a new image, with a hash of its own, at every call
        $imagick = new Imagick();
        $imagick->newPseudoImage(800, 500, 'plasma:fractal');
        $imagick->setImageFormat(ImageEntity::FORMAT_PNG);

        $tmpFile = $this->arrTmpFiles[] = tempnam(sys_get_temp_dir(), 'tli_images_delete_');
        file_put_contents($tmpFile, $imagick->getImageBlob());

        $image =
            static::getService(Factory::class)->createImageEditor()
                ->createFromUploadedFile(
                    new UploadedFile($tmpFile, uniqid(self::FIXTURE_TITLE_PREFIX) . '.png', null, null, true /* test mode */)
                );

        $this->arrImages[ $image->getId() ] = $image;

        $arrDates = array_filter([
            'updated_at'    => $updatedAt?->format('Y-m-d H:i:s'),
            'orphaned_at'   => $orphanedAt?->format('Y-m-d H:i:s')
        ]);

        if( !empty($arrDates) ) {
            static::getEntityManager()->getConnection()->update('image', $arrDates, ['id' => $image->getId()]);
        }

        return $image;
    }


    private function createArticleWith(ImageEditor $image) : ArticleEditor
    {
        $factory = static::getService(Factory::class);

        $system  = $factory->createUser()->load(User::ID_SYSTEM);

        $article =
            $factory->createArticleEditor()
                ->setTitle( uniqid(self::FIXTURE_TITLE_PREFIX) )
                ->setFormat(ArticleEditor::FORMAT_ARTICLE)
                ->addAuthor($system)
                ->addImage($image, $system)
                ->save();

        $this->arrArticles[ $article->getId() ] = $article;
        return $article;
    }


    private function deleteArticle(ArticleEditor $article) : void
    {
        $articleId = $article->getId();
        $article->delete();
        unset($this->arrArticles[$articleId]);
    }


    private function updateOrphanedAt() : void { static::getService(ImageRepository::class)->updateOrphanedAt(); }


    /** read from the database: updateOrphanedAt() is a bulk update, the entities in memory don't see it */
    private function getOrphanedAt(ImageEditor $image) : ?DateTime
    {
        $orphanedAt =
            static::getEntityManager()->getConnection()
                ->fetchOne('SELECT orphaned_at FROM image WHERE id = ?', [$image->getId()]);

        return empty($orphanedAt) ? null : new DateTime($orphanedAt);
    }


    private function imageExists(int $imageId) : bool
    {
        return
            (bool)static::getEntityManager()->getConnection()
                ->fetchOne('SELECT COUNT(*) FROM image WHERE id = ?', [$imageId]);
    }


    private function runImagesDelete(array $arrOptions = []) : string
    {
        $output = new BufferedOutput();
        $result = static::getService(ImagesDeleteCommand::class)->run(new ArrayInput($arrOptions), $output);
        $text   = $output->fetch();

        $this->assertSame(ImagesDeleteCommand::SUCCESS, $result, $text);
        return $text;
    }
    //</editor-fold>
}
