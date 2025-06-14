<?php
namespace App\Controller;

use App\Service\FrontendHelper;
use App\Service\User;
use http\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class CacheHolePunchingController extends BaseController
{
    #[Route('/ajax/userbar', name: 'app_cache-hole-punching_userbar', methods: ['POST'])]
    public function userbar(FrontendHelper $frontendHelper, User $user) : Response
    {
        $this->ajaxOnly();

        $siteUrl = $this->request->getSchemeAndHttpHost();
        $siteUrl = rtrim($siteUrl, '/');

        $originUrl = $this->request->get('originUrl') ?? $siteUrl;
        if( stripos($originUrl, $siteUrl) !== 0 ) {
            throw new InvalidArgumentException('Origin page is not valid');
        }

        if( empty($originUrl) ) {
            $originUrl = '/';
        }

        $newsletterUrl = $frontendHelper->getNewsletterHowToUrl();

        $userEntity = $this->getUser();

        if( empty($userEntity) ) {

            return $this->render('user/userbar-anonymous.html.twig', [
                'FrontendHelper'=> $frontendHelper,
                // the redirection works
                'loginUrl'      => $frontendHelper->getLoginUrl($originUrl),
                // the redirection DOESN'T WORK :-(
                'registerUrl'   => $frontendHelper->getRegisterUrl($originUrl),
                'newsletterUrl' => $newsletterUrl
            ]);
        }

        return $this->render('user/userbar-logged.html.twig', [
            'User'          => $user->setEntity($userEntity),
            'ucpUrl'        => $frontendHelper->getUcpUrl(),
            'newsletterUrl' => $newsletterUrl
        ]);
    }
}

