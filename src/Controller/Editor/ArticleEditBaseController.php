<?php
namespace App\Controller\Editor;

use App\Controller\BaseController;
use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Service\FrontendHelper;
use App\Service\Sentinel\ArticleSentinel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;


abstract class ArticleEditBaseController extends BaseController
{
    public function __construct(
        protected Factory $factory,
        protected ArticleEditor $articleEditor, protected Article $article, protected ArticleSentinel $sentinel,
        RequestStack $requestStack,
        protected FrontendHelper $frontendHelper, protected CsrfTokenManagerInterface $csrfTokenManager,
        protected Environment $twig
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }


    protected function loadArticleEditor(int $articleId) : ArticleEditor
    {
        $this->ajaxOnly();

        $this->sentinel->enforceLoggedUserOnly();

        $this->articleEditor->load($articleId);

        $this->sentinel
            ->setArticle($this->articleEditor)
            ->enforceCanEdit();

        return $this->articleEditor;
    }


    protected function jsonOKResponse(string $okMessage) : JsonResponse
    {
        $arrData = [
            "Article"       => $this->articleEditor,
            "Sentinel"      => $this->sentinel,
            "CurrentUser"   => $this->sentinel->getCurrentUser()
        ];

        return $this->json([
            "message"   => "✅ OK! $okMessage - " . (new \DateTime())->format('Y-m-d H:i:s'),
            "path"      => $this->articleEditor->getUrl(UrlGeneratorInterface::RELATIVE_PATH),
            "title"     => $this->articleEditor->getTitleForHTMLAttribute(),
            "strip"     => $this->twig->render('article/meta-strip.html.twig', $arrData),
            "bios"      => $this->twig->render('article/authors-bio.html.twig', $arrData),
            "tags"      => $this->twig->render('article/tags.html.twig',$arrData)
        ]);
    }
}
