<?php
namespace App\Controller;

use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Cms\File;
use App\Service\Cms\Image;
use App\Service\Cms\Tag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Pinky;


class TestController extends BaseController
{
    #[Route('/test/prevent-unused-service/', name: 'app_test_prevent-unused-service', condition: "'%kernel.environment%' == 'dev'")]
    public function preventUnusedService(
        Article $article, ArticleEditor $articleEditor,
        File $file, Image $image, Tag $tag
    ) : Response
    {
        dd("OK");
    }



    #[Route('/test/inky/', name: 'app_test_inky', condition: "'%kernel.environment%' == 'dev'")]
    public function inky() : Response
    {
        /*$transpiled = Pinky\transformString('<row>Contents</row>');
        echo $transpiled->saveHTML();
        dd("OK");*/
        return $this->render('newsletter/email.html.twig');
    }
}
