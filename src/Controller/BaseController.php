<?php
namespace App\Controller;

use App\Service\Cms\Paginator;
use App\Service\Factory;
use App\Service\YouTubeChannelApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;


abstract class BaseController extends AbstractController
{
    const int CACHE_DEFAULT_EXPIRY = 60 * 5;

    protected bool $cacheIsDisabled = false;
    protected Request $request;


    public function __construct(
        protected Factory $factory, protected Paginator $paginator,
        RequestStack $requestStack, protected TagAwareCacheInterface $cache, protected ParameterBagInterface $parameterBag,
        protected YouTubeChannelApi $YouTubeChannel, protected Environment $twig
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }


    protected function isCachable() : bool
    {
        if( $this->cacheIsDisabled ) {
           return false;
        }

        $isLogged           = !empty($this->getUser() ?? null);
        $currentUserIp      = $this->request->getClientIp();
        $currentUserIsLocal =
            !filter_var(
                $currentUserIp, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

        $currentEnv = $this->parameterBag->get("kernel.environment");
        $isDevEnv   = in_array($currentEnv, ["dev", "test"]);

        $result = !$isLogged && !$currentUserIsLocal && !$isDevEnv;
        return $result;
    }
}
