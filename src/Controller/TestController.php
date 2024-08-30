<?php
namespace App\Controller;

use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Cms\File;
use App\Service\Cms\Image;
use App\Service\Cms\Tag;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class TestController extends BaseController
{
    #[Route('/test/prevent-unused-service/', name: 'app_test_prevent-unused-service', condition: "'%kernel.environment%' == 'dev'")]
    #[NoReturn]
    public function preventUnusedService(
        Article $article, ArticleEditor $articleEditor,
        File $file, Image $image, Tag $tag
    ) : Response
    {
        dd("OK");
    }
}
