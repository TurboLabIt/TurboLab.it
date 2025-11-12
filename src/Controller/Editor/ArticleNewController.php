<?php
namespace App\Controller\Editor;

use App\Service\Cms\Article;
use App\Service\Cms\Tag;
use App\Service\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ArticleNewController extends ArticleEditBaseController
{
    const string TITLE_FIELD_NAME       = 'new-article-title';
    const string FORMAT_FIELD_NAME      = 'new-article-format';
    const string CSRF_TOKEN_ID          = self::TITLE_FIELD_NAME;


    #[Route('/scrivi', name: 'app_article_new', methods: ['GET'])]
    public function new() : Response
    {
        $currentUser = $this->getCurrentUser();

        if( empty($currentUser) ) {

            $templateFilename       = 'new-logged-out';
            $arrSideArticlesSlices  = null;

        } else {

            $templateFilename   = 'new';
            $sideArticles       = $this->factory->createArticleCollection()->loadLatestUpdatedListable();

            $numArticlesPerSlide= 7;
            $numSlides          = ceil( $sideArticles->count() / $numArticlesPerSlide );

            $arrSideArticlesSlices  = [];
            for($i = 0; $i < $numSlides; $i++) {

                $arrSideArticlesSlices[$i] =
                    $sideArticles->getItems($numArticlesPerSlide, $numArticlesPerSlide*$i, false, false);
            }
        }

        return $this->render("article/editor/$templateFilename.html.twig", [
            'metaTitle'                     => 'Scrivi nuovo articolo',
            'metaCanonicalUrl'              => $this->generateUrl('app_article_new', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'activeMenu'                    => '',
            'FrontendHelper'                => $this->frontendHelper,
            'ArticleHowTo'                  => $this->factory->createArticle()->load(Article::ID_PUBLISH_ARTICLE),
            'currentUserUrl'                => $currentUser?->getUrl(),
            'currentUserArticlesNum'        => $currentUser?->getArticlesNum(),
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
            'formatActionSponsor'           => Article::FORMAT_ACTION_SPONSOR,
            'csrfTokenFieldName'            => static::CSRF_TOKEN_PARAM_NAME,
            'csrfToken'                     => $this->csrfTokenManager->getToken(static::CSRF_TOKEN_ID)->getValue()
        ]);
    }


    #[Route('/scrivi/salva',  name: 'app_article_new_submit', methods: ['POST'])]
    public function submit() : Response
    {
        $this->loginRequired();

        //$this->validateCsrfToken();

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

        if( $newArticleFormat == Article::FORMAT_ACTION_SPONSOR ) {

            $newArticleFormat = Article::FORMAT_NEWS;
            $newArticleAuthor = $this->factory->createUser()->load(User::ID_SYSTEM);

            $this->articleEditor->setTags([
                $this->factory->createTag()->load(Tag::ID_SPONSOR),
                $this->factory->createTag()->load(Tag::ID_GAMES),
                $this->factory->createTag()->load(Tag::ID_CRYPTOCURRENCIES),
                $this->factory->createTag()->load(Tag::ID_WINDOWS),
                $this->factory->createTag()->load(Tag::ID_SECURITY),
            ], $newArticleAuthor);

        } else {

            $newArticleAuthor = $this->sentinel->getCurrentUserAsAuthor();
        }


        $this->articleEditor
            ->setFormat($newArticleFormat)
            ->addAuthor($newArticleAuthor)
            ->autotag($newArticleAuthor)
            ->save();

        return $this->redirect( $this->articleEditor->getUrl() );
    }
}
