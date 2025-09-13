<?php
namespace App\Controller;

use App\Service\Cms\Visit;
use App\Service\PhpBB\ForumUrlGenerator;
use App\Service\User;
use http\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;


class AjaxController extends BaseController
{
    #[Route('/ajax/userbar', name: 'app_ajax_userbar', methods: ['POST'])]
    public function userbar(User $user, ForumUrlGenerator $forumUrlGenerator) : Response
    {
        $this->ajaxOnly();

        $siteUrl = $this->request->getSchemeAndHttpHost();
        $siteUrl = rtrim($siteUrl, '/');

        // $originUrl is no longer used below
        $originUrl = $this->request->get('originUrl') ?? $siteUrl;
        if( stripos($originUrl, $siteUrl) !== 0 ) {
            throw new InvalidArgumentException('Origin page is not valid');
        }

        if( empty($originUrl) ) {
            $originUrl = '/';
        }

        $userEntity = $this->getUser();

        if( empty($userEntity) ) {

            $html =
                $this->cache->get("userbar_anonymous", function(ItemInterface $cacheItem) use($forumUrlGenerator) {

                    $cacheItem->expiresAfter(3600 * 72);

                    return
                        $this->renderView('user/userbar-anonymous.html.twig', [
                            'forumUrl'      => $forumUrlGenerator->generateHomeUrl(),
                            // the redirect (via parameter) would work
                            'loginUrl'      => $forumUrlGenerator->generateLoginUrl(),
                            // the redirect (via parameter) DOESN'T WORK
                            'registerUrl'   => $forumUrlGenerator->generateRegisterUrl(),
                            'newsletterUrl' => $this->frontendHelper->getNewsletterHowToUrl()
                        ]);
                });

        } else {

            $cacheKey = "userbar_loggedin_" . $userEntity->getId();

            $html =
                $this->cache->get($cacheKey, function(ItemInterface $cacheItem) use($user, $userEntity) {

                    $cacheItem->expiresAfter(3600 * 72);

                    return
                        $this->renderView('user/userbar-logged.html.twig', [
                            'User'          => $user->setEntity($userEntity),
                            'ucpUrl'        => $this->frontendHelper->getUcpUrl(),
                            'newsletterUrl' => $this->frontendHelper->getNewsletterHowToUrl()
                        ]);
                });
        }

        return new Response($html);
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
        $oCms   = $this->factory->createService($cmsType)->load($cmsId);
        $user   = $this->getCurrentUserAsAuthor();

        return $this->json( $visit->visit($oCms, $user) );
    }
}

