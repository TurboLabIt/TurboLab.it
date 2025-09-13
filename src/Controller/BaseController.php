<?php
namespace App\Controller;

use App\Exception\NotImplementedException;
use App\Service\Cms\Paginator;
use App\Service\Factory;
use App\Service\FrontendHelper;
use App\Service\User;
use Error;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
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
    const ?string CSRF_TOKEN_ID         = null;
    const string CSRF_TOKEN_PARAM_NAME  = '_csrf_token';

    protected bool $cacheIsDisabled = false;
    protected Request $request;


    public function __construct(
        protected Factory $factory, protected Paginator $paginator,
        RequestStack $requestStack, protected TagAwareCacheInterface $cache,
        protected ParameterBagInterface $parameterBag, protected FrontendHelper $frontendHelper,
        protected Environment $twig, protected CsrfTokenManagerInterface $csrfTokenManager
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }


    protected function getCurrentUser() : ?User { return $this->factory->getCurrentUser(); }

    protected function getCurrentUserAsAuthor() : ?User { return $this->factory->getCurrentUserAsAuthor(); }


    protected function tliStandardControllerResponse(array $arrCacheTags, ?int $page, ?callable $fxBuildHtml = null) : Response
    {
        $page = empty($page) ? 1 : $page;

        if( !$this->isCachable() ) {

            $buildHtmlResult = empty($fxBuildHtml) ? $this->buildHtmlNumPage($page) : $fxBuildHtml($page);
            return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
        }

        $cacheKey = reset($arrCacheTags) . '_page_' . $page;

        $buildHtmlResult =
            $this->cache->get($cacheKey, function(ItemInterface $cacheItem) use($cacheKey, $fxBuildHtml, $page, $arrCacheTags) {

                $buildHtmlResult = empty($fxBuildHtml) ? $this->buildHtmlNumPage($page) : $fxBuildHtml($page);

                if( is_string($buildHtmlResult) ) {

                    $cacheItem->expiresAfter(static::CACHE_DEFAULT_EXPIRY);
                    $cacheItem->tag( array_merge([$cacheKey], $arrCacheTags ) );

                } else {

                    $cacheItem->expiresAfter(-1);
                }

                return $buildHtmlResult;
            });

        return is_string($buildHtmlResult) ? new Response($buildHtmlResult) : $buildHtmlResult;
    }


    protected function isCachable(bool $notIfLoggedIn = false) : bool
    {
        if( $this->cacheIsDisabled ) {
           return false;
        }

        if( $notIfLoggedIn && !empty($this->getUser()?->getId()) ) {
            return false;
        }

        $isLocal =
            !filter_var(
                $this->request->getClientIp(), FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

        $isCacheWarmer = !empty( $this->request->headers->get(static::CACHE_WARMER_HEADER) );

        if( $isLocal && $isCacheWarmer ) {
            return true;
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


    protected function validateCsrfToken(?string $errorMessage = null, null|int|string $tokenId = null, ?string $tokenParamName = null)
    {
        $tokenParamName ??= static::CSRF_TOKEN_PARAM_NAME;
        $csrfToken = $this->request->get($tokenParamName);

        $tokenId ??= static::CSRF_TOKEN_ID;
        $oToken = new CsrfToken($tokenId, $csrfToken);

        if( !$this->csrfTokenManager->isTokenValid($oToken) ) {

            $errorMessage ??= 'Verifica di sicurezza CSRF fallita. Prova di nuovo';
            throw $this->createAccessDeniedException($errorMessage);
        }
    }


    protected function textErrorResponse(Exception|Error $ex, ?string $emoji = '🚨') : Response
    {
        if( method_exists($ex, 'getStatusCode') ) {

            $statusCode = $ex->getStatusCode();

        } elseif($ex instanceof AccessDeniedException) {

            $statusCode = $ex->getCode();

        } else {

            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        $message = $ex->getMessage() ?: 'Internal Server Error';
        $message = trim($message);
        $message = trim($emoji . " " . $message);

        $response = new Response($message, $statusCode);
        $response->headers->set('Content-Type', 'text/plain');

        return $response;
    }
}
