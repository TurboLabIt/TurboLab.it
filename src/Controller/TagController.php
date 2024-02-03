<?php
namespace App\Controller;

use App\Service\Cms\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class TagController extends BaseController
{
    public function __construct(protected Tag $tag)
    { }


    #[Route('/{tagSlugDashId<[^/]+-[1-9]+[0-9]*>}', name: 'app_tag')]
    public function index(string $tagSlugDashId, Request $request) : Response
    {
        $tag = $this->tag->loadBySlugDashId($tagSlugDashId);

        $tagRealUrl = $tag->checkRealUrl($tagSlugDashId);
        if( !empty($tagRealUrl) ) {
            return $this->redirect($tagRealUrl, Response::HTTP_MOVED_PERMANENTLY);
        }

        $tag
            ->setClientIpAddress( $request->getClientIp() )
            ->countOneView();

        return $this->render('tag/index.html.twig', [
            'Tag' => $tag
        ]);
    }


    #[Route('/{id<[1-9]+[0-9]*>}', name: 'app_tag_shorturl')]
    public function shortUrl(int $id) : Response
    {
        $tag = $this->tag->load($id);
        return $this->redirect($tag->getUrl(), Response::HTTP_MOVED_PERMANENTLY);
    }


    #[Route('/tag/{tag<[^/]+>}', name: 'app_tag_legacy')]
    public function legacyUrl(string $tag) : Response
    {
        $tag = $this->tag->loadByTitleOrSlug($tag);
        return $this->redirect($tag->getUrl(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
