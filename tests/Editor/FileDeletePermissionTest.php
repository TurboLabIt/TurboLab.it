<?php
namespace App\Tests\Editor;

use App\Controller\Editor\FileEditController;
use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\File as FileEntity;
use App\Entity\PhpBB\User as UserEntity;
use App\Service\Cms\Article;
use App\Service\Factory;
use App\Service\User;
use App\Tests\BaseT;
use SortDirection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Removing a Download from the last article that uses it deletes it, but only for its authors and the editors
 * (docs/downloadable-files.md, Permessi). A co-author of the article who didn't create the Download only detaches
 * it: the Download stays on TurboLab.it, orphaned, listed in /scarica/da-controllare.
 *
 * The fixture Download is authored by the Tester, and hangs only on an article System authors. Neither is an editor.
 */
class FileDeletePermissionTest extends BaseT
{
    private const string FIXTURE_TITLE_PREFIX = 'tli-test-file-delete-permission-';

    private ?int $fileId = null;


    public function testACoAuthorWhoDidNotCreateItOnlyDetachesIt() : void
    {
        static::loginAsSystem();
        $articleId      = $this->articleTheSystemAuthors();
        $this->fileId   = $this->createFileByTheTester($articleId);

        static::loginAsSystem();
        $response = $this->callDetachFromArticle($this->fileId, $articleId);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        static::loginAsSystem();
        $entity = static::getEntityManager()->find(FileEntity::class, $this->fileId);
        $this->assertNotNull($entity, "Someone who didn't create the Download must not delete it");

        $file = static::getService(Factory::class)->createFile()->setEntity($entity);
        $this->assertArrayNotHasKey($articleId, $file->getArticles(), "It must still be detached from the article");
    }


    public function testAnEditorDeletesIt() : void
    {
        static::loginAsSystem();
        $articleId      = $this->articleTheSystemAuthors();
        $this->fileId   = $this->createFileByTheTester($articleId);

        $this->loginAsEditor();
        $response = $this->callDetachFromArticle($this->fileId, $articleId);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $this->assertNull( static::getEntityManager()->find(FileEntity::class, $this->fileId),
            "The staff may delete anyone's Download from its last article");
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
                ->andWhere('a.id != :qualityTestId')->setParameter('qualityTestId', Article::ID_QUALITY_TEST)
                ->orderBy('a.publishingStatus', SortDirection::Ascending)->addOrderBy('a.id', SortDirection::Ascending)
                ->setMaxResults(1)
                ->getQuery()->getOneOrNullResult()['id'] ?? null;

        if( empty($articleId) ) {
            $this->markTestSkipped("The System user authors no article: nothing to attach a file to");
        }

        return (int)$articleId;
    }


    /** An external Download (nothing on disk) the Tester created, used only by $articleId */
    private function createFileByTheTester(int $articleId) : int
    {
        $factory = static::getService(Factory::class);
        $tester  = $factory->createUser()->load(User::ID_TESTER);

        $file =
            $factory->createFileEditor()
                ->createFromUrl(
                    'https://example.com/' . uniqid(self::FIXTURE_TITLE_PREFIX) . '.zip',
                    uniqid(self::FIXTURE_TITLE_PREFIX), 'zip'
                )
                ->addAuthor($tester);

        $factory->createArticleEditor()->load($articleId)->addFile($file, $factory->getCurrentUserAsAuthor());
        static::getEntityManager()->flush();

        return $file->getId();
    }


    private function loginAsEditor() : void
    {
        static::ensureKernelShutdown();
        static::$client = static::createClient();
        static::$client->setServerParameter('HTTP_HOST', $_ENV["APP_SITE_DOMAIN"]);
        static::$client->setServerParameter('HTTPS', 'https');

        $editor = static::getEntityManager()->find(UserEntity::class, User::ID_DEFAULT_ADMIN);
        $this->assertTrue($editor->isEditor(), "The default admin must be an editor for this test to mean anything");

        static::$client->loginUser($editor, 'tli_phpbb_cookies');
    }


    /** See FileEditCacheInvalidationTest::callEndpoint(): the stateless firewall keeps a KernelBrowser login out */
    private function callDetachFromArticle(int $fileId, int $articleId) : Response
    {
        static::getService('request_stack')->push(
            Request::create("/ajax/editor/files/detach-from-article/$fileId/$articleId", 'DELETE')
        );

        return static::getService(FileEditController::class)->detachFromArticle($fileId, $articleId);
    }
    //</editor-fold>
}
