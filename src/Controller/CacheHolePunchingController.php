<?php
namespace App\Controller;

use App\Service\PhpBB\ForumUrlGenerator;
use http\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class CacheHolePunchingController extends BaseController
{
    #[Route('/ajax/userbar', name: 'app_cache-hole-punching_userbar', methods: ['POST'])]
    public function userbar(ForumUrlGenerator $urlGenerator): Response
    {
        $this->ajaxOnly();

        $siteUrl = $this->request->getSchemeAndHttpHost();
        $siteUrl = rtrim($siteUrl, '/');

        $originUrl = $this->request->get('originUrl') ?? $siteUrl;
        if( stripos($originUrl, $siteUrl) !== 0 ) {
            throw new InvalidArgumentException('Origin page is not valid');
        }

        //$originUrl = substr($originUrl, strlen($siteUrl) );
        if( empty($originUrl) ) {
            $originUrl = '/';
        }

        $user = $this->getUser();

        if( empty($user) ) {
            return $this->render('user/userbar-anonymous.html.twig', [
                // the redirection works
                'loginUrl'      => $urlGenerator->generateLoginUrl($originUrl),
                // the redirection DOESN'T WORK :-(
                'registerUrl'   => $urlGenerator->generateRegisterUrl($originUrl)
            ]);
        }

        return $this->render('user/userbar-logged.html.twig', [
            // the redirection works
            'loginUrl'      => $urlGenerator->generateLoginUrl($originUrl),
            // the redirection DOESN'T WORK :-(
            'registerUrl'   => $urlGenerator->generateRegisterUrl($originUrl)
        ]);
    }
}

