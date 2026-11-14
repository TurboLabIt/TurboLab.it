<?php
namespace App\Controller\Editor;

use App\Service\Cms\Article;
use App\Service\Cms\File;
use App\Service\Cms\FileEditor;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


class FileEditController extends ArticleEditBaseController
{
    protected FileEditor $fileEditor;


    #[Route('/ajax/editor/files/upload-from-modal', name: 'app_file_edit-upload_from_modal', methods: ['POST'])]
    public function uploadFromModal(#[MapQueryParameter] int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $uploadedFiles = $this->request->files->get('files', []);
            $title         = trim( (string)$this->request->request->get('title', '') );

            if( empty($title) ) {
                throw new BadRequestHttpException("Il titolo è obbligatorio.");
            }

            $currentUserAsAuthor = $this->sentinel->getCurrentUserAsAuthor();

            $files =
                $this->factory->createFileEditorCollection()
                    ->setFromUpload($uploadedFiles, $currentUserAsAuthor, $title);

            $this->articleEditor->addFiles($files, $currentUserAsAuthor);
            $this->factory->getEntityManager()->flush();
            $this->clearCachedArticlePages([$this->articleEditor]);

            $fileEditor = null;
            foreach($files as $f) { $fileEditor = $f; break; }

            if( empty($fileEditor) ) {
                throw new BadRequestHttpException("Nessun file ricevuto.");
            }

            return $this->buildCreatedFileJsonResponse( $fileEditor, $files->isReused( $fileEditor->getId() ) );

        } catch(UniqueConstraintViolationException $ex) {

            return $this->buildDuplicateTitleResponse($ex);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/files/create-from-url', name: 'app_file_edit-create_from_url', methods: ['POST'])]
    public function createFromUrl(#[MapQueryParameter] int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $url    = trim( (string)$this->request->request->get('url', '') );
            $title  = trim( (string)$this->request->request->get('title', '') );
            $format = trim( (string)$this->request->request->get('format', '') );

            if( empty($url) || empty($title) || empty($format) ) {
                throw new BadRequestHttpException("URL, titolo e formato sono obbligatori.");
            }

            if( !File::isValidExternalUrl($url) ) {
                throw new BadRequestHttpException("L'URL inserito non è valido.");
            }

            $currentUserAsAuthor = $this->sentinel->getCurrentUserAsAuthor();

            // one item, one Download: the same URL (tracking parameters aside) reuses the existing one
            $fileEditor = null;
            foreach( $this->factory->createFileEditorCollection()->loadByEquivalentUrl($url) as $existing ) {
                $fileEditor = $existing;
                break;
            }

            $isReused = !empty($fileEditor);

            if( !$isReused ) {

                $fileEditor =
                    $this->factory->createFileEditor()
                        ->createFromUrl($url, $title, $format);

                $fileEditor->addAuthor($currentUserAsAuthor);
            }

            $this->articleEditor->addFiles([$fileEditor], $currentUserAsAuthor);
            $this->factory->getEntityManager()->flush();
            $this->clearCachedArticlePages([$this->articleEditor]);

            return $this->buildCreatedFileJsonResponse($fileEditor, $isReused);

        } catch(UniqueConstraintViolationException $ex) {

            return $this->buildDuplicateTitleResponse($ex);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    /**
     * "Prima si cerca, poi si crea": the Downloads matching what the author is about to add, by content hash
     * (computed in the browser) or by URL, plus the similar ones that could be an older version of it
     */
    #[Route('/ajax/editor/files/lookup', name: 'app_file_edit-lookup', methods: ['GET'])]
    public function lookup(#[MapQueryParameter] int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            $hash   = (string)$this->request->query->get('hash', '');
            $url    = trim( (string)$this->request->query->get('url', '') );
            $name   = trim( (string)$this->request->query->get('name', '') );

            if( preg_match('/^[a-f0-9]{32}$/', $hash) === 1 ) {

                $matches = $this->factory->createFileCollection()->loadByHash($hash);

            } elseif( $url !== '' ) {

                $matches = $this->factory->createFileCollection()->loadByEquivalentUrl($url);

            } else {

                throw new BadRequestHttpException("Indica il contenuto (hash) o l'URL da cercare.");
            }

            $similar = $this->factory->createFileCollection();
            if( $matches->count() == 0 && $name !== '' ) {
                $similar->loadSimilarByTitle($name);
            }

            return $this->json([
                'matchesNum'    => $matches->count(),
                'matchesHtml'   => $matches->count() == 0 ? null :
                    $this->twig->render('search/results-link-file.html.twig', ['LocalResults' => $matches]),
                'similarNum'    => $similar->count(),
                'similarHtml'   => $similar->count() == 0 ? null :
                    $this->twig->render('search/results-link-file.html.twig', [
                        'LocalResults'  => $similar,
                        'Replaceable'   => true,
                        'ArticleId'     => $articleId
                    ])
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    // "Sostituisci": a new version of an existing Download (uploaded file or URL), then linked from this article
    #[Route('/ajax/editor/files/replace/{fileId<[1-9]+[0-9]*>}', name: 'app_file_edit-replace', methods: ['POST'])]
    public function replace(int $fileId, #[MapQueryParameter] int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);
            $this->loadFileEditor($fileId);

            $uploadedFiles  = $this->request->files->get('files', []);
            $url            = trim( (string)$this->request->request->get('url', '') );

            if( !empty($uploadedFiles) ) {

                $this->fileEditor->replaceWithUploadedFile( reset($uploadedFiles) );

            } elseif( $url !== '' ) {

                if( !File::isValidExternalUrl($url) ) {
                    throw new BadRequestHttpException("L'URL inserito non è valido.");
                }

                // a local file's format was its extension: the author picks (or confirms) the one of the URL
                $format = trim( (string)$this->request->request->get('format', '') );
                $this->fileEditor->replaceWithUrl($url, $format === '' ? null : $format);

            } else {

                throw new BadRequestHttpException("Indica il nuovo file o il nuovo URL.");
            }

            $this->articleEditor->addFiles([$this->fileEditor], $this->sentinel->getCurrentUserAsAuthor());
            $this->factory->getEntityManager()->flush();

            $this->clearCachedArticlePages( [$this->articleEditor, ...array_values( $this->fileEditor->getArticles() )] );

            return $this->buildCreatedFileJsonResponse($this->fileEditor);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/files/get-modal/{fileId<[1-9]+[0-9]*>}/{articleId<[1-9]+[0-9]*>}', name: 'app_file_edit-get_modal', methods: ['GET'])]
    public function getEditModal(int $fileId, int $articleId) : Response
    {
        try {
            $this->loadFileEditor($fileId);

            return $this->json([
                "title" => "📝 Modifica Download: " . $this->fileEditor->getTitle() . " | #" . $this->fileEditor->getId(),
                "body"  => $this->twig->render('file/editor/modal.html.twig', [
                    'File'      => $this->fileEditor,
                    'Article'   => $this->factory->createArticle()->load($articleId),
                    'Formats'   => $this->factory->createFileCollection()->getFormats()
                ])
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/file/update/{fileId<[1-9]+[0-9]*>}/{articleId<[1-9]+[0-9]*>}', name: 'app_editor_file_update', methods: ['POST'])]
    public function update(int $fileId, int $articleId) : JsonResponse|Response
    {
        try {

            // title and format only: a new file or URL goes through replace(), with its checks
            $this->loadFileEditor($fileId)
                ->setTitle( $this->request->request->get('title') )
                ->setFormat( $this->request->request->get('format') )
                ->save();

            $this->clearCachedArticlePages( $this->fileEditor->getArticles() );

            return $this->render('article/files.html.twig', [
                'Article'           => $this->factory->createArticle()->load($articleId),
                'BitTorrentGuide'   => $this->factory->createArticle()->load(Article::ID_BITTORRENT_GUIDE),
                'EmuleGuide'        => $this->factory->createArticle()->load(Article::ID_EMULE_GUIDE)
            ]);

        } catch(UniqueConstraintViolationException $ex) {

            if( $ex->getCode() != 1062 || stripos($ex, 'Duplicate entry') === false ) {
                throw $ex;
            }

            $title = $this->fileEditor->getTitle();
            $originalFileUrl = $this->factory->createFile()->loadByTitle($title)->getUrl();

            return
                $this->textErrorResponse(
                    new ConflictHttpException(
                        "Impossibile salvare: esiste già un file dal titolo: $title ( $originalFileUrl ). " . PHP_EOL . PHP_EOL .
                        "Per favore, presta la massima attenzione a non creare file duplicati. " . PHP_EOL . PHP_EOL .
                        "🆕 Se è una nuova versione di quel Download, usa «Sostituisci» su quello, invece di crearne un altro. " . PHP_EOL . PHP_EOL .
                        "🎏 Se invece si tratta dello stesso file in formato diverso, esplicitalo nel titolo. Ad es.: per Linux, (portable), ..."
                    )
                );

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/files/detach-from-article/{fileId<[1-9]+[0-9]*>}/{articleId<[1-9]+[0-9]*>}', name: 'app_file_edit-detach_from_article', methods: ['DELETE'])]
    public function detachFromArticle(int $fileId, int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);
            $fileEditor = $this->factory->createFileEditor()->load($fileId);

            $arrUsedBy = $fileEditor->getArticles();

            if( !array_key_exists($articleId, $arrUsedBy) ) {
                throw new AccessDeniedException("Questo file non è collegato all'articolo indicato");
            }

            // the last article using it ➡ delete the file directly, if allowed: its authors and the editors.
            // A co-author of the article who didn't create it only detaches it: the Download stays, orphaned
            if( count($arrUsedBy) < 2 && $fileEditor->isEditable() ) {

                $fileEditor->delete();

            // used by other articles too, or not deletable by this user ➡ just detach from the current article
            } else {

                $this->articleEditor->removeFile($fileEditor);
            }


            // there is no need to save() the article here
            $this->factory->getEntityManager()->flush();
            $this->clearCachedArticlePages([$this->articleEditor]);

            return new Response('OK');

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    protected function loadFileEditor(int $fileId) : FileEditor
    {
        $this->ajaxOnly();

        $this->loginRequired();

        return
            $this->fileEditor =
                $this->factory->createFileEditor()->load($fileId)
                    ->enforceCanEdit();
    }


    /**
     * Article pages are cached for anonymous visitors and show their files (downloads box, body links): a file
     * change must clear every page showing it. Listing pages don't show files: no need for clearCachedArticle()
     */
    protected function clearCachedArticlePages(iterable $articles) : static
    {
        $arrCacheTags = [];
        foreach($articles as $article) {
            $arrCacheTags[] = $article->getCacheKey();
        }

        $this->cache->invalidateTags($arrCacheTags);
        return $this;
    }


    protected function buildCreatedFileJsonResponse(FileEditor $fileEditor, bool $isReused = false) : JsonResponse
    {
        return $this->json([
            'id'                    => $fileEditor->getId(),
            'reused'                => $isReused,
            'downloadUrl'           => $fileEditor->getUrl(),
            'title'                 => $fileEditor->getTitle(),
            'userPerceivedFileName' => $fileEditor->getUserPerceivedFileName(),
            'attachedFilesHtml'     => $this->twig->render('article/files.html.twig', [
                'Article'           => $this->articleEditor,
                'BitTorrentGuide'   => $this->factory->createArticle()->load(Article::ID_BITTORRENT_GUIDE),
                'EmuleGuide'        => $this->factory->createArticle()->load(Article::ID_EMULE_GUIDE)
            ])
        ]);
    }


    protected function buildDuplicateTitleResponse(UniqueConstraintViolationException $ex) : Response
    {
        if( $ex->getCode() != 1062 || stripos($ex, 'Duplicate entry') === false ) {
            throw $ex;
        }

        return
            $this->textErrorResponse(
                new ConflictHttpException(
                    "Impossibile salvare: esiste già un file con questo titolo. " . PHP_EOL . PHP_EOL .
                    "Per favore, presta la massima attenzione a non creare file duplicati. " . PHP_EOL . PHP_EOL .
                    "🆕 Se è una nuova versione di quel Download, usa «Sostituisci» su quello, invece di crearne un altro. " . PHP_EOL . PHP_EOL .
                    "🎏 Se invece si tratta dello stesso file in formato diverso, esplicitalo nel titolo. Ad es.: per Linux, (portable), ..."
                )
            );
    }
}
