<?php
namespace App\Tests\Editor;

use App\Controller\Editor\FileEditController;
use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\Article;
use App\Service\Factory;
use App\Service\User;
use App\Tests\BaseT;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;


/**
 * The article page is cached for anonymous visitors, tagged with the article's cache key, and it shows the
 * article's files: the downloads box (title, format, size) and the body links (URL, "Scarica …" title). The file
 * endpoints used to change all of that without invalidating the page, so until it expired visitors kept seeing a
 * deleted download (now a 404), a stale title, and no trace of a newly attached file.
 *
 * isCachable() is false in the test env, so nothing gets cached over HTTP here: each test plants a probe item
 * tagged like the article page, calls the endpoint and checks that the probe is gone.
 */
class FileEditCacheInvalidationTest extends BaseT
{
    private const string FIXTURE_TITLE_PREFIX = 'tli-test-file-cache-';

    private array $arrFileIds = [];


    public function testUploadClearsTheArticlePage() : void
    {
        static::loginAsSystem();
        [$articleId] = $this->articlesTheSystemAuthors(1);
        $probe = $this->cacheProbe($articleId);

        $tmpPath = tempnam(sys_get_temp_dir(), 'tli_test_file_cache_');
        file_put_contents($tmpPath, uniqid(self::FIXTURE_TITLE_PREFIX) . "\n");

        $response = $this->callEndpoint(
            Request::create('/ajax/editor/files/upload-from-modal', 'POST',
                ['title' => uniqid(self::FIXTURE_TITLE_PREFIX)], [],
                ['files' => [new UploadedFile($tmpPath, 'fixture.txt', null, null, true)]]
            ),
            fn(FileEditController $controller) => $controller->uploadFromModal($articleId)
        );

        $this->arrFileIds[] = json_decode($response->getContent(), true)['id'] ?? null;

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $this->assertCacheProbeCleared($probe, "Uploading a file must clear the page of the article it's attached to");
    }


    public function testCreateFromUrlClearsTheArticlePage() : void
    {
        static::loginAsSystem();
        [$articleId] = $this->articlesTheSystemAuthors(1);
        $probe = $this->cacheProbe($articleId);

        $response = $this->callEndpoint(
            Request::create('/ajax/editor/files/create-from-url', 'POST', [
                'url'       => 'https://example.com/' . uniqid(self::FIXTURE_TITLE_PREFIX) . '.zip',
                'title'     => uniqid(self::FIXTURE_TITLE_PREFIX),
                'format'    => 'zip'
            ]),
            fn(FileEditController $controller) => $controller->createFromUrl($articleId)
        );

        $this->arrFileIds[] = json_decode($response->getContent(), true)['id'] ?? null;

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $this->assertCacheProbeCleared($probe, "Creating a file from a URL must clear the page of the article it's attached to");
    }


    public function testUpdateClearsEveryArticlePageShowingTheFile() : void
    {
        static::loginAsSystem();
        $articleIds = $this->articlesTheSystemAuthors(2);
        $fileId     = $this->createFileFixture($articleIds);
        $probes     = array_map(fn(int $articleId) => $this->cacheProbe($articleId), $articleIds);

        $response = $this->callEndpoint(
            Request::create("/ajax/editor/file/update/$fileId/{$articleIds[0]}", 'POST', [
                'title'         => uniqid(self::FIXTURE_TITLE_PREFIX),
                'format'        => 'zip',
                'remote-url'    => 'https://example.com/' . uniqid(self::FIXTURE_TITLE_PREFIX) . '.zip'
            ]),
            fn(FileEditController $controller) => $controller->update($fileId, $articleIds[0])
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        foreach($probes as $probe) {
            $this->assertCacheProbeCleared($probe,
                "A renamed file shows its new title on every article using it: all their pages must be cleared");
        }
    }


    public function testDetachClearsTheArticlePage() : void
    {
        static::loginAsSystem();
        $articleIds = $this->articlesTheSystemAuthors(2);
        $fileId     = $this->createFileFixture($articleIds);
        $probe      = $this->cacheProbe($articleIds[0]);

        $response = $this->callDetachFromArticle($fileId, $articleIds[0]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $this->assertNotNull( static::getEntityManager()->find(FileEntity::class, $fileId),
            "A file used by another article must only be detached: this test is about the detach branch");

        $this->assertCacheProbeCleared($probe, "Detaching a file must clear the page of the article it leaves");
    }


    public function testDeleteClearsTheArticlePage() : void
    {
        static::loginAsSystem();
        [$articleId] = $this->articlesTheSystemAuthors(1);
        $fileId     = $this->createFileFixture([$articleId]);
        $probe      = $this->cacheProbe($articleId);

        $response = $this->callDetachFromArticle($fileId, $articleId);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $this->assertNull( static::getEntityManager()->find(FileEntity::class, $fileId),
            "A file used by this article only must be deleted: this test is about the delete branch");

        $this->assertCacheProbeCleared($probe, "Deleting a file must clear the page of the article it was attached to");
    }


    protected function tearDown() : void
    {
        foreach($this->arrFileIds as $fileId) {
            $this->deleteFileFixture($fileId);
        }

        $this->arrFileIds = [];
        parent::tearDown();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures ***">

    /** Articles the System user authors, drafts first: nobody crawls them while a fixture hangs on them */
    private function articlesTheSystemAuthors(int $num) : array
    {
        $arrIds =
            static::getEntityManager()->createQueryBuilder()
                ->select('a.id')->from(ArticleAuthor::class, 'aa')
                ->join('aa.article', 'a')
                ->where('aa.user = :userId')->setParameter('userId', User::ID_SYSTEM)
                ->andWhere('a.id != :qualityTestId')->setParameter('qualityTestId', Article::ID_QUALITY_TEST)
                ->orderBy('a.publishingStatus', 'ASC')->addOrderBy('a.id', 'ASC')
                ->setMaxResults($num)
                ->getQuery()->getSingleColumnResult();

        if( count($arrIds) < $num ) {
            $this->markTestSkipped("The System user authors less than $num article(s): nothing to attach a file to");
        }

        return array_map('intval', $arrIds);
    }


    /**
     * An external file (nothing on disk) uploaded by System and attached to $articleIds. The kernel is rebooted
     * afterwards, so the endpoint loads everything from the DB as a real request would: ArticleEditor::addFile()
     * doesn't update the File side of the junction in memory.
     */
    private function createFileFixture(array $articleIds) : int
    {
        $factory    = static::getService(Factory::class);
        $author     = $factory->getCurrentUserAsAuthor();

        $file =
            $factory->createFileEditor()
                ->createFromUrl(
                    'https://example.com/' . uniqid(self::FIXTURE_TITLE_PREFIX) . '.zip',
                    uniqid(self::FIXTURE_TITLE_PREFIX), 'zip'
                )
                ->addAuthor($author);

        foreach($articleIds as $articleId) {
            $factory->createArticleEditor()->load($articleId)->addFile($file, $author);
        }

        static::getEntityManager()->flush();

        $this->arrFileIds[] = $fileId = $file->getId();

        static::loginAsSystem();
        return $fileId;
    }


    /** A cache item tagged like the article page: it lives until that page is invalidated */
    private function cacheProbe(int $articleId) : string
    {
        $key = uniqid('tli-test-file-cache-probe-');
        $tag = static::getService(Factory::class)->createArticle()->load($articleId)->getCacheKey();

        static::getService(TagAwareCacheInterface::class)->get($key, function(ItemInterface $item) use($tag) {
            $item->tag([$tag]);
            return 'cached';
        });

        return $key;
    }


    private function assertCacheProbeCleared(string $key, string $message) : void
    {
        $cache = static::getService(TagAwareCacheInterface::class);
        $value = $cache->get($key, fn() => 'recomputed');
        $cache->delete($key);

        $this->assertSame('recomputed', $value, $message);
    }


    /**
     * The endpoints are AJAX calls behind a login, and the firewall is stateless, so a KernelBrowser login doesn't
     * reach them: push the Request the controller reads in its constructor, then call the action on the service
     */
    private function callEndpoint(Request $request, callable $fxAction) : Response
    {
        static::getService('request_stack')->push($request);
        return $fxAction( static::getService(FileEditController::class) );
    }


    private function callDetachFromArticle(int $fileId, int $articleId) : Response
    {
        return $this->callEndpoint(
            Request::create("/ajax/editor/files/detach-from-article/$fileId/$articleId", 'DELETE'),
            fn(FileEditController $controller) => $controller->detachFromArticle($fileId, $articleId)
        );
    }


    private function deleteFileFixture(?int $fileId) : void
    {
        if( empty($fileId) ) {
            return;
        }

        static::ensureKernelShutdown();
        static::loginAsSystem();

        $em     = static::getEntityManager();
        $entity = $em->find(FileEntity::class, $fileId);

        if( $entity === null ) {
            return;
        }

        $filePath = static::getService(Factory::class)->createFile()->setEntity($entity)->getOriginalFilePath();
        if( !empty($filePath) && is_file($filePath) ) {
            @unlink($filePath);
        }

        $em->remove($entity);
        $em->flush();
    }

    //</editor-fold>
}
