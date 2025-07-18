<?php
namespace App\Controller\Editor;

use App\Controller\BaseController;
use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Service\FrontendHelper;
use Error;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;


class ArticleEditorController extends BaseController
{
    const string TITLE_FIELD_NAME       = 'new-article-title';
    const string FORMAT_FIELD_NAME      = 'new-article-format';
    const string CSRF_TOKEN_ID          = self::TITLE_FIELD_NAME;


    public function __construct(
        protected Factory $factory,
        protected ArticleEditor $articleEditor, protected Article $article, RequestStack $requestStack,
        protected FrontendHelper $frontendHelper, protected CsrfTokenManagerInterface $csrfTokenManager,
        protected Environment $twig
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🆕 /scrivi ***">
    #[Route('/scrivi', name: 'app_editor_new', methods: ['GET'])]
    public function new() : Response
    {
        $currentUser = $this->factory->getCurrentUser();

        if( empty($currentUser) ) {

            $templateFilename = 'new-logged-out';
            $arrSideArticlesSlices = null;

        } else {

            $templateFilename = 'new';

            $sideArticles = $this->factory->createArticleCollection()->loadLatestUpdatedListable();

            $numArticlesPerSlide = 7;
            $numSlides = ceil( $sideArticles->count() / $numArticlesPerSlide );

            $arrSideArticlesSlices  = [];
            for($i = 0; $i < $numSlides; $i++) {

                $arrSideArticlesSlices[$i] =
                    $sideArticles->getItems($numArticlesPerSlide, $numArticlesPerSlide*$i, false, false);
            }
        }

        return $this->render("article/editor/$templateFilename.html.twig", [
            'metaTitle'                     => 'Scrivi nuovo articolo',
            'metaCanonicalUrl'              => $this->generateUrl('app_editor_new', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'activeMenu'                    => '',
            'FrontendHelper'                => $this->frontendHelper,
            'ArticleHowTo'                  => $this->factory->createArticle()->load(Article::ID_PUBLISH_ARTICLE),
            'currentUserUrl'                => $currentUser?->getUrl(),
            'CurrentUserDraftArticles'      => $currentUser?->getArticlesDraft(),
            'CurrentUserInReviewArticles'   => $currentUser?->getArticlesInReview(),
            'CurrentUserPublishedArticles'  => $currentUser?->getArticlesLatestPublished(),
            'CurrentUserKoArticles'         => $currentUser?->getArticlesKo(),
            'SideArticlesSlices'            => $arrSideArticlesSlices,
            'Views'                         => $this->frontendHelper->getViews()->get(['bozze', 'finiti']),
            //
            'titleFieldName'                => static::TITLE_FIELD_NAME,
            'formatFieldName'               => static::FORMAT_FIELD_NAME,
            'formatArticle'                 => Article::FORMAT_ARTICLE,
            'formatNews'                    => Article::FORMAT_NEWS,
            'csrfTokenFieldName'            => static::CSRF_TOKEN_PARAM_NAME,
            'csrfToken'                     => $this->csrfTokenManager->getToken(static::CSRF_TOKEN_ID)->getValue()
        ]);
    }


    #[Route('/scrivi/salva',  name: 'app_editor_new_submit', methods: ['POST'])]
    public function submit() : Response
    {
        $currentUser = $this->factory->getCurrentUser();

        if( empty($currentUser) ) {

            throw $this->createAccessDeniedException(
                'Non sei loggato! Solo gli utenti registrati possono creare nuovi articoli.'
            );
        }

        $this->validateCsrfToken();

        // TODO zaneee! Rate limiting on new article

        $newArticleTitle = $this->request->get(static::TITLE_FIELD_NAME);

        $this->articleEditor->setTitle($newArticleTitle);

        $articles =
            $this->factory->createArticleCollection()->loadByComparableSearch(
                $this->articleEditor->getTitleComparable(), 'title'
            );

        if( $articles->count() ) {
            return $this->redirect( $articles->first()->getUrl() );
        }

        $newArticleFormat = $this->request->get(static::FORMAT_FIELD_NAME);

        /*
         * $currentUser is unknown to Doctrine: if we try to set it as Author directly:
         * A new entity was found through the relationship 'App\Entity\Cms\ArticleAuthor#user' that was not configured to cascade persist operations for entity: App\Entity\PhpBB\User@--
         */
        $currentUserId = $currentUser->getId();
        $author = $this->factory->createUser()->load($currentUserId);

        $this->articleEditor
            ->setFormat($newArticleFormat)
            ->addAuthor($author)
            ->autotag($author)
            ->save();

        return $this->redirect( $this->articleEditor->getUrl() );
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 📜 Title and Body ***">
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}', name: 'app_editor_article_update', methods: ['POST'])]
    public function update(int $articleId) : JsonResponse|Response
    {
        try {
            $this->loadArticleEditor($articleId);

            foreach(['title', 'body'] as $param) {

                $value  = $this->request->get($param);
                $method = "set" . ucfirst($param);
                $this->articleEditor->$method($value);
            }

            $this->articleEditor->save();
            return $this->jsonOKResponse("Articolo salvato");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 👥 Authors ***">
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/get-authors-modal', name: 'app_editor_article_get-authors-modal', methods: ['GET'])]
    public function getAuthorsModal(int $articleId) : Response
    {
        try {
            $this->loadArticleEditor($articleId);

            return $this->json([
                "title" => "👥 Modifica autori",
                "body" => $this->twig->render('article/editor/authors-modal.html.twig', [
                    "Article"       => $this->articleEditor,
                    "LatestAuthors" => $this->factory->createUserCollection()->loadLatestAuthors()
                ])
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/authors/', name: 'app_editor_article_authors-autocomplete', methods: ['GET'])]
    public function authorsAutocomplete() : Response
    {
        $this->ajaxOnly();

        try {

            if( empty($this->getUser()) ) {
                throw $this->createAccessDeniedException('Non sei loggato!');
            }

            $username = $this->request->get('username');

            $authors = $this->factory->createUserCollection();

            empty($username) ? $authors->loadLatestAuthors() : $authors->loadBySearchUsername($username);

            return $this->render('article/editor/authors-autocomplete.html.twig', [
                'Authors' => $authors
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/set-authors', name: 'app_editor_article_set-authors', methods: ['POST'])]
    public function setAuthors(int $articleId) : JsonResponse|Response
    {
        try {

            $arrAuthorIds = $this->request->get('authors') ?? [];
            $this->loadArticleEditor($articleId)->setAuthorsFromIds($arrAuthorIds);
            $this->factory->getEntityManager()->flush();
            return $this->jsonOKResponse("Autori salvati");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🏷️ Tags ***">
    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/get-tags-modal', name: 'app_editor_article_get-tags-modal', methods: ['GET'])]
    public function getTagsModal(int $articleId) : Response
    {
        try {
            $this->loadArticleEditor($articleId);

            return $this->json([
                "title" => "🏷️ Modifica tag",
                "body" => $this->twig->render('article/editor/tags-modal.html.twig', [
                    "Article"           => $this->articleEditor,
                    "CommonTagGroups"   => $this->factory->createTagCollection()->getCommonGrouped()
                ])
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }

    #[Route('/ajax/editor/tags/', name: 'app_editor_article_tags-autocomplete', methods: ['GET'])]
    public function tagsAutocomplete() : Response
    {
        $this->ajaxOnly();

        try {

            if( empty($this->getUser()) ) {
                throw $this->createAccessDeniedException('Non sei loggato!');
            }

            $tag = $this->request->get('tag');

            return $this->render('article/editor/tags-autocomplete.html.twig', [
                'Tags' => $this->factory->createTagCollection()->loadBySearchTagOrCreate($tag)
            ]);

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }


    #[Route('/ajax/editor/article/{articleId<[1-9]+[0-9]*>}/set-tags', name: 'app_editor_article_set-tags', methods: ['POST'])]
    public function setTags(int $articleId) : JsonResponse|Response
    {
        try {

            $arrIdsAndTags = $this->request->get('tags') ?? [];
            $this->loadArticleEditor($articleId)->setTagsFromIdsAndTags($arrIdsAndTags);
            $this->factory->getEntityManager()->flush();
            return $this->jsonOKResponse("Tag salvati");

        } catch(Exception|Error $ex) { return $this->textErrorResponse($ex); }
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** ⛑️ Helpers ***">
    protected function loadArticleEditor(int $articleId) : ArticleEditor
    {
        $this->ajaxOnly();

        if( empty($this->getUser()) ) {
            throw $this->createAccessDeniedException('Non sei loggato!');
        }

        $this->articleEditor->load($articleId);

        if( !$this->articleEditor->currentUserCanEdit() ) {
            throw $this->createAccessDeniedException('Non sei autorizzato a modificare questo articolo');
        }

        return $this->articleEditor;
    }


    protected function jsonOKResponse(string $okMessage) : JsonResponse
    {
        return $this->json([
            "message"   => "✅ OK! $okMessage - " . (new \DateTime())->format('Y-m-d H:i:s'),
            "path"      => $this->articleEditor->getUrl(UrlGeneratorInterface::RELATIVE_PATH),
            "title"     => $this->articleEditor->getTitleForHTMLAttribute(),
            "strip"     => $this->twig->render('article/meta-strip.html.twig', [
                "Article" => $this->articleEditor,
            ]),
            "bios"      => $this->twig->render('article/authors-bio.html.twig', [
                "Article" => $this->articleEditor
            ]),
            "tags"      => $this->twig->render('article/tags.html.twig', [
                "Article" => $this->articleEditor
            ])
        ]);
    }
    //</editor-fold>
}
