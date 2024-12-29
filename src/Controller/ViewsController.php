<?php
namespace App\Controller;

use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;


class ViewsController extends BaseController
{
    const string SECTION_SLUG = "viste";


    #[Route('/' . self::SECTION_SLUG . '/{slug}/{page<[0-9]*>}', name: 'app_views_legacy', requirements: ['slug' => 'tutti'])]
    public function legacy($page = 1) : Response
        { return $this->redirectToRoute('app_home', [], Response::HTTP_MOVED_PERMANENTLY); }


    #[Route('/' . self::SECTION_SLUG . '/{slug}/{page<0|1>}', name: 'app_views_multi_0-1')]
    public function appViewsMulti0Or1($slug) : Response
        { return $this->redirectToRoute('app_views_multi', ["slug" => $slug], Response::HTTP_MOVED_PERMANENTLY); }


    #[Route('/' . self::SECTION_SLUG . '/{slug}', name: 'app_views_multi')]
    public function multi($slug) : Response
    {
        return new Response('OK');
    }
}
