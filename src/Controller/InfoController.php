<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class InfoController extends AbstractController
{
    #[Route('/statistiche', name: 'app_stats')]
    public function stats(): Response
    {
        return new Response("Pagina in aggiornamento", Response::HTTP_SERVICE_UNAVAILABLE);
    }


    #[Route('/info', name: 'app_info')]
    public function info(): Response
    {
        return new Response("Pagina in aggiornamento", Response::HTTP_SERVICE_UNAVAILABLE);
    }


    #[Route('/calendario', name: 'app_calendar')]
    public function calendar(): Response
    {
        return new Response("Pagina in aggiornamento", Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
