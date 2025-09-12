<?php
namespace App\Controller;

use App\Service\Cms\Visit;
use App\Service\FrontendHelper;
use App\Service\User;
use http\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class AjaxController extends BaseController
{
    #[Route('/ajax/userbar', name: 'app_ajax_userbar', methods: ['POST'])]
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
                // the redirect works
                'loginUrl'      => $frontendHelper->getLoginUrl($originUrl),
                // the redirect DOESN'T WORK :-(
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


    #[Route('/ajax/count-visit', name: 'app_ajax_count-visit', methods: ['POST'])]
    public function countView(Visit $visit) : JsonResponse
    {
        $this->ajaxOnly();

        if( !$visit->isCountable() ) {

            return $this->json([
                'views' => null,
                'new'   => null,
            ]);
        }

        $cmsId  = (int)$this->request->get('cmsId');
        $cmsType= $this->request->get('cmsType');
        $user   = $this->getCurrentUser();
        $oCms   = $this->factory->createService($cmsType)->load($cmsId);

        return $this->json( $visit->visit($oCms, $user) );
    }
}

