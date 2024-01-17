<?php
namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;


class ImageController extends BaseController
{
    #[Route('/immagini/{size<min|med|max>}/{imageSlugDashId<[^/]+-[1-9]+[0-9]*>}.{format<[^/]+>}', name: 'app_image')]
    public function index($size, $imageSlugDashId): Response
    {
        dd("OK");
    }
}
