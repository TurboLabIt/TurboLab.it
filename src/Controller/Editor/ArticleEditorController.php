<?php
namespace App\Controller\Editor;

use App\Controller\BaseController;
use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Service\FrontendHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;


class ArticleEditorController extends BaseController
{
    const string TITLE_FIELD_NAME       = 'new-article-title';
    const string FORMAT_FIELD_NAME      = 'new-article-format';
    const string CSRF_TOKEN_ID          = self::TITLE_FIELD_NAME;


    public function __construct(
        protected Factory $factory,
        protected ArticleEditor $articleEditor, protected Article $article, RequestStack $requestStack,
        protected FrontendHelper $frontendHelper, protected CsrfTokenManagerInterface $csrfTokenManager
    )
        { $this->request = $requestStack->getCurrentRequest(); }


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

        $newArticleTitle    = $this->request->get(static::TITLE_FIELD_NAME);
        $newArticleFormat   = $this->request->get(static::FORMAT_FIELD_NAME);

        /*
         * $currentUser is unknown to Doctrine: if we try to set it as Author directly:
         * A new entity was found through the relationship 'App\Entity\Cms\ArticleAuthor#user' that was not configured to cascade persist operations for entity: App\Entity\PhpBB\User@--
         */
        $currentUserId = $currentUser->getId();
        $author = $this->factory->createUser()->load($currentUserId);

        $this->articleEditor
            ->setTitle($newArticleTitle)
            ->setFormat($newArticleFormat)
            ->addAuthor($author)
            ->save();

        return $this->redirect( $this->articleEditor->getUrl() );
    }


    #[Route('/editor/article/body/{articleId<[1-9]+[0-9]*>}', name: 'app_editor_article_body')]
    public function body(int $articleId) : JsonResponse
    {
        $this->articleEditor->load($articleId);

        $html = $this->articleEditor->getBodyForDisplay();

        $this->articleEditor->load($articleId)
            ->setBody($html)
            ->save();

        $this->article->load($articleId);

        return $this->json([
            "result"    => "OK",
            "body"      => $this->article->getBodyForDisplay()
        ]);
    }
}
