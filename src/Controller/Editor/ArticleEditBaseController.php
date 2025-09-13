<?php
namespace App\Controller\Editor;

use App\Controller\BaseController;
use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Service\FrontendHelper;
use App\Service\Mailer;
use App\Service\Sentinel\ArticleSentinel;
use DateTime;
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


    protected function handleNotification(Mailer $mailer, string &$jsonOkMessage) : static
    {
        $sent =
            $mailer
                ->block(false)
                ->sendIfHasToRecipients();

        if($sent) {
            $jsonOkMessage .= ". 📨 Email di notifica inviata a " .
                implode(', ', array_map(fn($recipient) => $recipient->getName(), $mailer->getTo()));
        }

        $arrCc = $mailer->getCc();
        if( $sent && !empty($arrCc) ) {
            $jsonOkMessage .= " (e in CC a te, " .
                implode(', ', array_map(fn($recipient) => $recipient->getName(), $arrCc)) . ")";
        }

        return $this;
    }


    protected function jsonOKResponse(string $okMessage) : JsonResponse
    {
        $arrData = [
            "Article"       => $this->articleEditor,
            "Sentinel"      => $this->sentinel,
            "CurrentUser"   => $this->getCurrentUser()
        ];

        return $this->json([
            "message"   => "✅ OK! $okMessage - " . (new DateTime())->format('Y-m-d H:i:s'),
            "path"      => $this->articleEditor->getUrl(UrlGeneratorInterface::RELATIVE_PATH),
            "title"     => $this->articleEditor->getTitleForHTMLAttribute(),
            "strip"     => $this->twig->render('article/meta-strip.html.twig', $arrData),
            "bios"      => $this->twig->render('article/authors-bio.html.twig', $arrData),
            "tags"      => $this->twig->render('article/tags.html.twig',$arrData)
        ]);
    }


    protected function clearCachedArticle(?DateTime $previousPublishedAt = null, ?array $arrPreviousTags = null, ?array $arrPreviousAuthors = null) : void
    {
        $publishedAt = $this->articleEditor->getPublishedAt();
        $arrTagsToDelete = [];

        if(
            (
                !empty($previousPublishedAt) &&
                $previousPublishedAt >= (new DateTime())->modify('-30 days') &&
                $previousPublishedAt <= (new DateTime())
            ) ||
            (
                !empty($publishedAt) &&
                $publishedAt >= (new DateTime())->modify('-30 days') &&
                $publishedAt <= (new DateTime())
            )
        ) {
            $arrTagsToDelete = ['app_home_page_1', 'app_feed', 'app_news_page_1'];

            $arrArticleTags = array_merge($arrPreviousTags ?? [], $this->article->getTags());
            foreach($arrArticleTags as $tag) {
                $arrTagsToDelete[] = $tag->getCacheKey() . '_page_1';
            }

            $arrArticleAuthors = array_merge($arrPreviousAuthors ?? [], $this->article->getAuthors());
            foreach($arrArticleAuthors ?? [] as $author) {
                $arrTagsToDelete[] = $author->getCacheKey() . '_page_1';
            }
        }

        $arrTagsToDelete = array_merge([$this->articleEditor->getCacheKey()], $arrTagsToDelete);

        $this->cache->invalidateTags($arrTagsToDelete);
    }
}
