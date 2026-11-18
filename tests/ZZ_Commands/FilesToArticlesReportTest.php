<?php
namespace App\Tests\ZZ_Commands;

use App\Command\FilesToArticlesCommand;
use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\Article;
use App\Service\Cms\File;
use App\Service\Factory;
use App\Service\User;
use App\Tests\BaseT;
use SortDirection;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use TurboLabIt\BaseCommand\Service\ProjectDir;


/**
 * "File da controllare" (/scarica/da-controllare) lists every Download attached to an article and not linked in its
 * text, however recent: the list is var/files-orphans.json, which FilesToArticles writes, in a dry run too. It used to
 * leave a Download out until both the article and the link were 9 months old, so every new case stayed hidden, and
 * any save of an article hid the Downloads it still didn't link (2026-11-18). The 9 months still hold for the images,
 * where they keep an article that is being written from losing its gallery (ImagesToArticlesCommand).
 */
class FilesToArticlesReportTest extends BaseT
{
    private const string FIXTURE_TITLE_PREFIX = 'tli-test-files-to-articles-';

    private ?int $fileId = null;


    public function testADownloadJustAttachedAndNotLinkedIsListed() : void
    {
        static::loginAsSystem();
        $articleId      = $this->articleTheSystemAuthors();
        $this->fileId   = $this->attachNewDownload($articleId);

        $command    = static::getService(FilesToArticlesCommand::class);
        $result     = $command->run(new ArrayInput(['--dry-run' => true]), new BufferedOutput());
        $this->assertSame(FilesToArticlesCommand::SUCCESS, $result);

        $listPath = static::getService(ProjectDir::class)->getVarDirFromFilePath(File::ATTACHED_BUT_UNUSED_FILE_NAME);
        $list = json_decode((string)file_get_contents($listPath), true);

        $this->assertIsArray($list, "FilesToArticles must write the list as JSON");
        $this->assertArrayHasKey($articleId, $list,
            "An article with a Download attached and not linked must be listed, even when it changed just now");
        $this->assertArrayHasKey($this->fileId, $list[$articleId]['Files'],
            "A Download attached and not linked must be listed, even when it was attached just now");
    }


    protected function tearDown() : void
    {
        if( !empty($this->fileId) ) {

            static::loginAsSystem();
            $em     = static::getEntityManager();
            $entity = $em->find(FileEntity::class, $this->fileId);

            if( $entity !== null ) {
                $em->remove($entity);
                $em->flush();
            }
        }

        $this->fileId = null;
        parent::tearDown();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures ***">
    private function articleTheSystemAuthors() : int
    {
        $articleId =
            static::getEntityManager()->createQueryBuilder()
                ->select('a.id')->from(ArticleAuthor::class, 'aa')
                ->join('aa.article', 'a')
                ->where('aa.user = :userId')->setParameter('userId', User::ID_SYSTEM)
                // the quality-test fixture stays as it is; the "about us" article is never listed, by design
                ->andWhere('a.id NOT IN (:excluded)')->setParameter('excluded', [Article::ID_QUALITY_TEST, Article::ID_ABOUT_US])
                ->orderBy('a.publishingStatus', SortDirection::Ascending)->addOrderBy('a.id', SortDirection::Ascending)
                ->setMaxResults(1)
                ->getQuery()->getOneOrNullResult()['id'] ?? null;

        if( empty($articleId) ) {
            $this->markTestSkipped("The System user authors no article: nothing to attach a file to");
        }

        return (int)$articleId;
    }


    /** A new external Download (nothing on disk), attached to $articleId and linked nowhere: its text can't know it */
    private function attachNewDownload(int $articleId) : int
    {
        $factory = static::getService(Factory::class);

        $file =
            $factory->createFileEditor()
                ->createFromUrl(
                    'https://example.com/' . uniqid(self::FIXTURE_TITLE_PREFIX) . '.zip',
                    uniqid(self::FIXTURE_TITLE_PREFIX), 'zip'
                );

        $factory->createArticleEditor()->load($articleId)->addFile($file, $factory->getCurrentUserAsAuthor());
        static::getEntityManager()->flush();

        return $file->getId();
    }
    //</editor-fold>
}
