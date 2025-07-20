<?php
namespace App\Controller\Editor;

use App\Controller\BaseController;
use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Service\FrontendHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;


abstract class ArticleEditBaseController extends BaseController
{

    public function __construct(
        protected Factory $factory,
        protected ArticleEditor $articleEditor, protected Article $article, RequestStack $requestStack,
        protected FrontendHelper $frontendHelper, protected CsrfTokenManagerInterface $csrfTokenManager,
        protected Environment $twig
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }


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
}
