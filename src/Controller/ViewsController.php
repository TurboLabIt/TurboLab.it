<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ViewsController extends BaseController
{
    const string SECTION_SLUG = "viste";


    #[Route('/' . self::SECTION_SLUG . '/{slug}/{page<[0-9]*>}', name: 'app_views_legacy', requirements: ['slug' => 'tutti'])]
    public function legacy(?int $page = 1) : Response { return $this->redirectToRoute('app_home', [], Response::HTTP_MOVED_PERMANENTLY); }
}
