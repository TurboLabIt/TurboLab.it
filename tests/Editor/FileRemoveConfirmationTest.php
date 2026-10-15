<?php
namespace App\Tests\Editor;

use App\Entity\Cms\Article as ArticleEntity;
use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleFile;
use App\Entity\Cms\File as FileEntity;
use App\Entity\PhpBB\User as UserEntity;
use App\Service\Cms\Article;
use App\Service\Factory;
use App\Service\User;
use App\Tests\BaseT;


/**
 * The remove button of each downloadable file asks for confirmation inline (templates/article/files.html.twig). A
 * file other articles use too gets «Scollega», and its confirmation lists those articles: the author sees where
 * the file stays, an editor where to go to remove it everywhere. Every article is named, as in any listing:
 * Article::getTitle() already masks what the viewer can't list (a blocked article), while draft titles are
 * listable on purpose. Only what the viewer can open is a link.
 *
 * Built in memory, no DB write: the current article is authored by the logged-in System user, the others aren't.
 */
class FileRemoveConfirmationTest extends BaseT
{
    public function testDetachConfirmationListsTheOtherArticles() : void
    {
        static::loginAsSystem();

        $published  = $this->buildArticle(PHP_INT_MAX - 2, 'Altro articolo "pubblicato" & co.', Article::PUBLISHING_STATUS_PUBLISHED);
        $draft      = $this->buildArticle(PHP_INT_MAX - 3, 'Bozza di un altro autore', Article::PUBLISHING_STATUS_DRAFT);
        $blocked    = $this->buildArticle(PHP_INT_MAX - 4, 'Articolo bloccato di un altro autore', Article::PUBLISHING_STATUS_KO);

        $html = $this->renderDownloadableFiles([$published, $draft, $blocked]);

        $this->assertStringContainsString('negli altri 3 articoli che lo usano', $html);

        $publishedUrl = $this->createArticle($published)->getUrl();
        $this->assertMatchesRegularExpression(
            '#<a href="' . preg_quote($publishedUrl, '#') . '" target="_blank">\s*Altro articolo &quot;pubblicato&quot; &amp; co\.\s*</a>#',
            $html, "A published article using the file must be listed, linked and escaped"
        );

        $this->assertStringContainsString('Bozza di un altro autore', $html,
            "Draft titles are listable: someone else's draft must be named");
        $this->assertStringNotContainsString('href="' . $this->createArticle($draft)->getUrl() . '"', $html,
            "Someone else's draft must not be linked: the viewer can't open it");

        $this->assertStringNotContainsString('Articolo bloccato di un altro autore', $html,
            "A blocked article isn't listable: its title must be masked");
        $this->assertStringContainsString('Articolo non disponibile', $html);

        $this->assertStringContainsString('al prossimo salvataggio, o comunque entro qualche ora', $html,
            "FilesToArticles re-attaches a file the body still links even without a save: the warning must say so");
    }


    public function testDeleteConfirmationForAFileOnlyThisArticleUses() : void
    {
        static::loginAsSystem();

        $html = $this->renderDownloadableFiles([]);

        $this->assertStringContainsString('verrà eliminato definitivamente da TurboLab.it', $html);
        $this->assertStringNotContainsString('verrà scollegato', $html);
        $this->assertStringNotContainsString('tli-file-other-articles', $html);
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures ***">

    /** The downloads box of a draft System authors, with one external file also attached to $otherArticles */
    private function renderDownloadableFiles(array $otherArticles) : string
    {
        $current = $this->buildArticle(PHP_INT_MAX - 1, 'Articolo corrente', Article::PUBLISHING_STATUS_DRAFT, true);

        $file =
            (new FileEntity())
                ->setId(PHP_INT_MAX)
                ->setTitle('File di prova')
                ->setFormat('zip')
                ->setUrl('https://example.com/file-di-prova.zip');

        foreach([$current, ...$otherArticles] as $article) {
            $file->addArticle( (new ArticleFile())->setArticle($article) );
        }

        // both sides share the junction to the current article, as when Doctrine loads it
        $current->addFile( $file->getArticles()->first() );

        $factory = static::getService(Factory::class);

        return static::getService('twig')->render('article/files.html.twig', [
            'Article'           => $factory->createArticle($current),
            'BitTorrentGuide'   => $factory->createArticle()->load(Article::ID_BITTORRENT_GUIDE),
            'EmuleGuide'        => $factory->createArticle()->load(Article::ID_EMULE_GUIDE)
        ]);
    }


    private function buildArticle(int $id, string $title, int $publishingStatus, bool $authoredBySystem = false) : ArticleEntity
    {
        $article =
            (new ArticleEntity())
                ->setId($id)
                ->setTitle($title)
                ->setPublishingStatus($publishingStatus);

        if($authoredBySystem) {
            $article->addAuthor(
                (new ArticleAuthor())->setUser( static::getEntityManager()->find(UserEntity::class, User::ID_SYSTEM) )
            );
        }

        return $article;
    }


    private function createArticle(ArticleEntity $entity) : Article
    {
        return static::getService(Factory::class)->createArticle($entity);
    }

    //</editor-fold>
}
