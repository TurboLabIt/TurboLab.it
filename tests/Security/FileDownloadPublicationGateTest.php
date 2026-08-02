<?php
namespace App\Tests\Security;

use App\Controller\Editor\FileEditController;
use App\Entity\Cms\Article as ArticleEntity;
use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleFile;
use App\Entity\Cms\File as FileEntity;
use App\Entity\PhpBB\User as UserEntity;
use App\Service\Cms\Article;
use App\Service\Cms\File;
use App\Service\Factory;
use App\Service\User;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * `/scarica/{id}` used to load the file by ID and hand it over — X-Sendfile for a local upload, `302` for an
 * external URL — with NO sentinel and NO look at the publishing status of the articles it hangs on: it was the
 * only CMS read path without a per-object gate (images go through ImageSentinel, articles through
 * ArticleSentinel::canView). `templates/article/files.html.twig` already hides the list behind
 * `Article.isReadable`, so the UI respected a rule the endpoint didn't. Two consequences, both anonymous and
 * with a sequential, enumerable ID (security-audit #45):
 *
 *   1. the attachments of a draft (or of a KO article) were downloadable by anyone guessing the ID;
 *   2. it is what made #41 abusable at scale — registration is self-service, so anybody could park an external
 *      URL on a draft no editor would ever see and mint a permanent turbolab.it redirect. #41 (redirecting to an
 *      arbitrary destination) stays WONTFIX on purpose: that IS the feature. The invariant this test defends is
 *      the other half — such a link must live on a PUBLISHED article, i.e. pass the same human review as any
 *      `<a href>` in an article body.
 *
 * The gate mirrors articles: `File::isVisitable()` (no publishing status of its own ⇒ inherited from the
 * articles it hangs on) and `FileSentinel::canView()` = `isVisitable() || canEdit(file) || canEditAnyArticle()`.
 * The third clause exists because `canEdit()` only knows the authors of the FILE: without it a co-author who
 * didn't upload the attachment would be locked out of the very draft they are writing.
 *
 * A 200/302 on a non-visible file is a security regression. A 403 on a published one is a functional
 * regression — the site distributes downloads, and breaking them silently is worse than the finding.
 */
class FileDownloadPublicationGateTest extends BaseT
{
    private const string FIXTURE_TITLE_PREFIX = 'tli-sec-download-gate-';
    private const string FIXTURE_PAYLOAD      = 'tli-sec-gate-orphan-payload';


    //<editor-fold defaultstate="collapsed" desc="*** 1️⃣ live HTTP: the gate is enforced on the endpoint ***">

    /**
     * An orphan file — attached to no article at all, exactly the state 3 of the 6 affected production rows are
     * in — must NOT be downloadable by an anonymous visitor. Before the fix this returned 200 + the bytes.
     *
     * 401, not 403: the sentinel throws AccessDeniedException (as every other enforce* in the app does) and,
     * with no `entry_point` on the stateless firewall, Symfony turns it into InsufficientAuthentication ➡ 401
     * for an anonymous token — no `WWW-Authenticate`, so no browser credentials prompt. A logged-in but
     * unauthorized caller gets the usual 403.
     */
    public function testOrphanFileIsNotDownloadableByAnonymous() : void
    {
        $fileId = null;

        try {
            $fileId = $this->createOrphanFileFixture();

            static::ensureKernelShutdown();
            $this->browse('/scarica/' . $fileId);

            $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED,
                "An orphan file must not be downloadable by an anonymous visitor (security-audit #45)");

            $this->assertStringNotContainsString(self::FIXTURE_PAYLOAD,
                (string)static::$client->getResponse()->getContent(),
                "The gate must stop the bytes, not merely change the status code");

        } finally {
            $this->deleteFileFixture($fileId);
        }
    }


    /** The whole point of the endpoint: a LOCAL file on a published article still downloads, unauthenticated. */
    public function testPublishedLocalFileStillDownloads() : void
    {
        // 👀 https://turbolab.it/scarica/1 — same fixture tests/Smoke/FileTest.php downloads
        $body = $this->fetchHtml('/scarica/1', Request::METHOD_GET, false);

        $this->assertResponseIsSuccessful("A file attached to a published article must stay downloadable");
        $this->assertNotEmpty($body);
    }


    /**
     * …and an EXTERNAL one still redirects. This is #41 itself, deliberately left alone: the 302 to whatever
     * destination the author chose IS the feature. Should this ever become an interstitial or a 403, it will be
     * a product decision, not a side effect of tightening the gate.
     */
    public function testPublishedExternalFileStillRedirects() : void
    {
        static::ensureKernelShutdown();
        $this->browse('/scarica/' . File::ID_LOGO);

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND,
            "An external file on a published article must still redirect to its destination (security-audit #41)");
        $this->assertNotEmpty( static::$client->getResponse()->headers->get('Location') );
    }

    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 2️⃣ File::isVisitable(): visibility is inherited ***">

    /**
     * @return array<string, array{0:array<int>, 1:bool}> label => [attached article statuses, expected]
     */
    public static function visibilityProvider() : array
    {
        return [
            'no article at all (orphan)'     => [[], false],
            'draft only'                     => [[Article::PUBLISHING_STATUS_DRAFT], false],
            'KO only'                        => [[Article::PUBLISHING_STATUS_KO], false],
            'draft + KO'                     => [[Article::PUBLISHING_STATUS_DRAFT, Article::PUBLISHING_STATUS_KO], false],
            'ready for review'               => [[Article::PUBLISHING_STATUS_READY_FOR_REVIEW], true],
            'published'                      => [[Article::PUBLISHING_STATUS_PUBLISHED], true],
            'draft + published'              => [[Article::PUBLISHING_STATUS_DRAFT, Article::PUBLISHING_STATUS_PUBLISHED], true],
        ];
    }


    /**
     * `PUBLISHING_STATUSES_VISIBLE` is [3, 5]: ReadyForReview is publicly readable by design, so a file on such
     * an article stays downloadable. ONE visible article is enough — a file shared between a draft and a
     * published article must not be collateral damage.
     */
    #[DataProvider('visibilityProvider')]
    public function testVisibilityIsInheritedFromArticles(array $articleStatuses, bool $expected) : void
    {
        $file = $this->buildDetachedFile($articleStatuses);

        $this->assertSame($expected, $file->isVisitable(),
            "A file attached to [" . implode(', ', $articleStatuses) . "] articles: isVisitable() must be " .
            var_export($expected, true));
    }

    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 3️⃣ FileSentinel::canView(): who gets past the gate ***">

    /** Anonymous: visibility is the only key. */
    public function testAnonymousCannotViewAnInvisibleFile() : void
    {
        $sentinel = static::getService(Factory::class)->createFileSentinel();

        $this->assertFalse($sentinel->canView( $this->buildDetachedFile([Article::PUBLISHING_STATUS_DRAFT]) ),
            "An anonymous visitor must not get past the gate on a draft's attachment");

        $this->assertTrue($sentinel->canView( $this->buildDetachedFile([Article::PUBLISHING_STATUS_PUBLISHED]) ),
            "An anonymous visitor must still get a published article's attachment");
    }


    /**
     * The co-author clause. The logged-in user (System — a plain REGISTERED account, NOT staff) is an author of
     * the draft but NOT an author of the file, so `isVisitable()` and `canEdit($file)` are both false: only
     * `canEditAnyArticle()` can open the gate. Without it, previewing your own co-written draft would 403.
     */
    public function testArticleAuthorCanViewTheAttachmentOfTheirOwnDraft() : void
    {
        static::loginAsSystem();

        $factory    = static::getService(Factory::class);
        $currentUser= $factory->getCurrentUser();

        $this->assertNotNull($currentUser, "The fixture user must be logged in for this assertion to mean anything");
        $this->assertFalse($currentUser->isEditor(),
            "User " . User::ID_SYSTEM . " must NOT be staff, or this test would pass through canEdit() instead");

        $file = $this->buildDetachedFile([Article::PUBLISHING_STATUS_DRAFT], authorOfArticles: true);

        $this->assertFalse($file->isVisitable(), "Precondition: the draft's attachment is not publicly visible");
        $this->assertTrue($factory->createFileSentinel($file)->canView(),
            "An author of the article must be able to download the attachment of their own draft");
    }


    /** …and the clause is scoped: being an author SOMEWHERE else opens nothing. */
    public function testArticleAuthorshipDoesNotLeakToUnrelatedFiles() : void
    {
        static::loginAsSystem();

        $factory = static::getService(Factory::class);
        $file    = $this->buildDetachedFile([Article::PUBLISHING_STATUS_DRAFT], authorOfArticles: false);

        $this->assertFalse($factory->createFileSentinel($file)->canView(),
            "Authorship on other articles must not unlock a draft's attachment the user has no part in");
    }

    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 4️⃣ #4 — detach can't reach a file outside the article ***">

    /**
     * `detachFromArticle` checked the edit permission on the article named in the URL, then loaded an ARBITRARY
     * fileId and, when that file hung on less than 2 articles, deleted it outright (row + bytes). Pairing a
     * target file with any article you control was therefore a delete primitive over most of the file table
     * (security-audit #4). The pair must now hold: the file has to be attached to that very article.
     *
     * The fixture is an orphan — 0 articles — so it lands in the DELETE branch: the exact path that used to
     * destroy someone else's file. Note what is deliberately NOT enforced here: `canEdit` on the file itself.
     * It keys on the authors of the FILE, so it would stop a co-author from detaching an attachment a fellow
     * author uploaded — the edit-side twin of the co-author case FileSentinel::canEditAnyArticle() handles.
     */
    public function testDetachCannotReachAFileNotAttachedToTheArticle() : void
    {
        static::loginAsSystem();

        $articleId = $this->anArticleTheCurrentUserAuthors();
        $fileId    = null;

        try {
            $fileId = $this->createOrphanFileFixture();

            $response = $this->callDetachFromArticle($fileId, $articleId);

            $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(),
                "Detaching a file that hangs on another article must be refused (security-audit #4)");

            $this->assertNotNull( static::getEntityManager()->find(FileEntity::class, $fileId),
                "The refused call must leave the file alive — this is the branch that used to delete it");

        } finally {
            $this->deleteFileFixture($fileId);
        }
    }


    /**
     * Control: the 403 above comes from the pairing check, NOT from the caller being unable to edit the article
     * in the first place. Same caller, same article, a file id that doesn't exist ⇒ the request travels past
     * every permission gate and dies on "not found".
     */
    public function testTheArticleGateItselfLetsTheAuthorThrough() : void
    {
        static::loginAsSystem();

        $response = $this->callDetachFromArticle(PHP_INT_MAX, $this->anArticleTheCurrentUserAuthors());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(),
            "An author must get past the article gate — otherwise the test above would pass for the wrong reason");
    }

    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures ***">

    /**
     * An in-memory File service wired to as many articles as $articleStatuses requires — no DB write, no
     * Meilisearch, nothing to clean up. $authorOfArticles adds the CURRENT user as author of every article
     * (never of the file, which is the point of the co-author assertions).
     */
    private function buildDetachedFile(array $articleStatuses, bool $authorOfArticles = false) : File
    {
        $factory    = static::getService(Factory::class);
        $fileEntity = (new FileEntity())->setId(PHP_INT_MAX)->setTitle('detached fixture');

        $userEntity =
            $authorOfArticles
                ? static::getEntityManager()->find(UserEntity::class, $factory->getCurrentUser()?->getId())
                : null;

        foreach($articleStatuses as $i => $status) {

            $articleEntity =
                (new ArticleEntity())
                    ->setId(PHP_INT_MAX - $i - 1)
                    ->setTitle('detached fixture article ' . $i)
                    ->setPublishingStatus($status);

            if( !empty($userEntity) ) {
                $articleEntity->addAuthor( (new ArticleAuthor())->setUser($userEntity) );
            }

            $fileEntity->addArticle( (new ArticleFile())->setArticle($articleEntity) );
        }

        return $factory->createFile()->setEntity($fileEntity);
    }


    /**
     * The detach endpoint is an AJAX DELETE behind a login, and the firewall is stateless (the phpBB cookies
     * are re-read on every request), so a KernelBrowser login does not survive to a second HTTP call. The
     * controller is a service, though: push a Request so BaseController can construct, then call the action.
     */
    private function callDetachFromArticle(int $fileId, int $articleId) : Response
    {
        static::getService('request_stack')->push(
            Request::create('/ajax/editor/files/detach-from-article/' . $fileId . '/' . $articleId, 'DELETE')
        );

        return static::getService(FileEditController::class)->detachFromArticle($fileId, $articleId);
    }


    /** Any article the logged-in fixture user authors, so the article-level gate is satisfied on its own merit. */
    private function anArticleTheCurrentUserAuthors() : int
    {
        $userId = static::getService(Factory::class)->getCurrentUser()?->getId();
        $this->assertNotEmpty($userId, "The fixture user must be logged in");

        $articleId =
            static::getEntityManager()->createQueryBuilder()
                ->select('IDENTITY(aa.article)')->from(ArticleAuthor::class, 'aa')
                ->where('aa.user = :userId')->setParameter('userId', $userId)
                ->orderBy('aa.article', 'ASC')->setMaxResults(1)
                ->getQuery()->getOneOrNullResult();

        if( empty($articleId) ) {
            $this->markTestSkipped("User $userId authors no article: nothing to exercise the article gate with");
        }

        return (int)$articleId[1];
    }


    /** A real, committed, local file attached to no article — the "orphan" state, for the HTTP assertions. */
    private function createOrphanFileFixture() : int
    {
        static::loginAsSystem();

        $tmpPath = tempnam(sys_get_temp_dir(), 'tli_sec_gate_');
        file_put_contents($tmpPath, self::FIXTURE_PAYLOAD . "\n");

        $uploaded = new UploadedFile($tmpPath, 'fixture.txt', null, null, true);

        $editor =
            static::getService(Factory::class)->createFileEditor()
                ->createFromUploadedFile($uploaded, self::FIXTURE_TITLE_PREFIX . uniqid());

        return $editor->getId();
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
