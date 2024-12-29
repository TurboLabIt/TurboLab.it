<?php
namespace App\Controller;

use App\Exception\NotImplementedException;
use App\Service\Cms\Paginator;
use App\Service\Factory;
use App\Service\FrontendHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;


abstract class BaseController extends AbstractController
{
    const int CACHE_DEFAULT_EXPIRY      = 60 * 10; // 10 minutes
    const string CACHE_WARMER_HEADER    = "x-tli-cache-warmer";

    protected bool $cacheIsDisabled = false;
    protected Request $request;


    public function __construct(
        protected Factory $factory, protected Paginator $paginator,
        RequestStack $requestStack, protected TagAwareCacheInterface $cache, protected ParameterBagInterface $parameterBag,
        protected FrontendHelper $frontendHelper, protected Environment $twig
    )
        { $this->request = $requestStack->getCurrentRequest(); }


    protected function tliStandardControllerResponse(array $arrCacheTags, ?int $page, ?callable $fxBuildHtml = null) : Response
    {
        $page = empty($page) ? 1 : $page;

        if( !$this->isCachable() ) {

            $buildHtmlResult = empty($fxBuildHtml) ? $this->buildHtmlNumPage($page) : $fxBuildHtml($page);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $cacheKey   = reset($arrCacheTags) . '_page_' . $page;
        $that       = $this;

        $buildHtmlResult =
            $this->cache->get($cacheKey, function(CacheItem $cache) use($cacheKey, $that, $fxBuildHtml, $page, $arrCacheTags) {

                $buildHtmlResult = empty($fxBuildHtml) ? $that->buildHtmlNumPage($page) : $fxBuildHtml($page);

                if( is_string($buildHtmlResult) ) {

                    $coldCacheStormBuster = 60 * rand(0, 10); // 0-5 minutes
                    $cache->expiresAfter(static::CACHE_DEFAULT_EXPIRY + $coldCacheStormBuster);
                    $cache->tag( array_merge([$cacheKey], $arrCacheTags ) );

                } else {

                    $cache->expiresAfter(-1);
                }

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function isCachable() : bool
    {
        if( $this->cacheIsDisabled ) {
           return false;
        }

        /*$isLogged = !empty($this->getUser() ?? null);
        if($isLogged) {
            return false;
        }*/

        $isLocal =
            !filter_var(
                $this->request->getClientIp(), FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

        $isCacheWarmer = !empty( $this->request->headers->get(static::CACHE_WARMER_HEADER) );

        if( $isLocal && $isCacheWarmer) {
            return true;
        }

        if($isLocal) {
            return false;
        }

        $currentEnv = $this->parameterBag->get("kernel.environment");
        if( in_array($currentEnv, ["dev", "test"]) ) {
            return false;
        }

        return true;
    }


    protected function buildHtmlNumPage(?int $page) : Response|string
    {
        throw new NotImplementedException(
            "BaseController::buildHtmlNumPag() requires a specific implementation in each controller"
        );
    }


    protected function ajaxOnly() : void
    {
        if( !$this->request->isXmlHttpRequest() ) {
            throw new BadRequestException('This page can only be requested via AJAX');
        }
    }
}
