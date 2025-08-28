<?php
namespace App\Controller;

use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Cms\File;
use App\Service\Cms\Image;
use App\Service\Cms\Tag;
use App\Service\FrontendHelper;
use App\Service\HtmlProcessorForDisplay;
use App\Service\HtmlProcessorForStorage;
use App\Service\Mailer;
use App\Service\User;
use App\Service\Views;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Pinky;


class TestController extends BaseController
{
    const string SECTION_SLUG = "test";

    #[Route('/' . self::SECTION_SLUG, name: 'app_test', condition: "'%kernel.environment%' == 'dev'")]
    public function index() : Response
    {
        return $this->render('test/index.html.twig', []);
    }


    #[Route('/' . self::SECTION_SLUG . '/base', name: 'app_test_base', condition: "'%kernel.environment%' == 'dev'")]
    public function base(FrontendHelper $frontendHelper) : Response
    {
        return $this->render('test/base.html.twig', [
            'activeMenu'        => 'home',
            'FrontendHelper'    => $frontendHelper,
        ]);
    }


    #[Route('/' . self::SECTION_SLUG . '/prevent-unused-service/', name: 'app_test_prevent-unused-service', condition: "'%kernel.environment%' == 'dev'")]
    public function preventUnusedService(
        Article $article, ArticleEditor $articleEditor,
        File $file, Image $image, Tag $tag, Views $views,
        HtmlProcessorForDisplay $htmlProcessorForDisplay,
        HtmlProcessorForStorage $htmlProcessorForStorage
    ) : Response
    {
        dd("OK");
    }


    #[Route('/' . self::SECTION_SLUG . '/newsletter/', name: 'app_test_newsletter', condition: "'%kernel.environment%' == 'dev'")]
    public function newsletter() : Response
    {
        /*$transpiled = Pinky\transformString('<row>Contents</row>');
        echo $transpiled->saveHTML();
        dd("OK");*/
        return $this->render('newsletter/email.html.twig');
    }


    #[Route('/' . self::SECTION_SLUG . '/email/', name: 'app_test_email', condition: "'%kernel.environment%' == 'dev'")]
    public function email(Mailer $mailer, Article $article, User $userPublisher) : Response
    {
        $email =
            $mailer
                ->buildNewAuthorAddedToArticle(
                    $article->load(Article::ID_QUALITY_TEST), $userPublisher->load(User::ID_DEFAULT_ADMIN)
                )
                ->getEmail();

        $mailer
            ->block(false)
            ->send();

        return $this->render( $email->getHtmlTemplate(), $email->getContext() );
    }
}
