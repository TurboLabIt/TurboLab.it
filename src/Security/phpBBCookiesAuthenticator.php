<?php
namespace App\Security;

use App\Entity\PhpBB\User;
use App\Repository\PhpBB\UserRepository;
use App\Trait\phpBBCookiesAuthenticatorTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LogoutEvent;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/tree/main/docs/users.md
 */
class phpBBCookiesAuthenticator extends AbstractAuthenticator implements EventSubscriberInterface
{
    use phpBBCookiesAuthenticatorTrait;

    public function __construct(protected Security $security, protected UserRepository $userRepository) {}


    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request) : ?bool
    {
        $route = $request->attributes->get('_route');

        if( str_starts_with($route, 'app_image') || str_starts_with($route, 'app_feed') ) {
            return false;
        }

        if( str_starts_with($route, 'app_') && str_ends_with($route, '_page_0-1') ) {
            return false;
        }

        if( str_starts_with($route, 'app_') && str_ends_with($route, '_shorturl') ) {
            return false;
        }

        return !in_array($route, static::AUTH_IGNORED_ROUTES);
    }


    protected function getUserFromPhpBBCookies(Request $request) : ?User
    {
        $arrLoginData = [];
        foreach(["sid", "u", 'k'] as $oneCookieName) {

            $value = trim( $request->cookies->get(self::COOKIE_BASENAME_PHPBB . $oneCookieName) ?? '' );
            if( empty($value) || $value == 1 ) {
                continue;
            }

            $arrLoginData[$oneCookieName] = $value;
        }

        if( empty($arrLoginData["sid"]) || empty($arrLoginData["u"]) ) {

            $this->removeNoRememberMeCookie();
            return null;
        }

        // "remember me" flow
        if( !empty($arrLoginData["k"]) ) {

            $user = $this->userRepository->findOneByUserSidKey($arrLoginData["u"], $arrLoginData["sid"], $arrLoginData["k"]);
            return empty($user) ? null : $user;
        }


        // "no-remember me" workaround flow
        $arrFallbackCookie = $this->getNoRememberMeCookie('../');
        if(
            empty($arrFallbackCookie) ||
            $arrLoginData["sid"] != $arrFallbackCookie["session_id"] ||
            $arrLoginData["u"] != $arrFallbackCookie["id"]
        ) {
            $this->removeNoRememberMeCookie();
            return null;
        }

        $user = $this->userRepository->findOneByUserSid($arrFallbackCookie["id"], $arrFallbackCookie["session_id"]);

        return empty($user) ? null : $user;
    }


    public function authenticate(Request $request) : Passport
    {
        /**
         * re-using symfony current user doesn't work bc
         * it wouldn't detect a logout started from phpBB
         * $user = $this->security->getUser();
         */

        $user = $this->getUserFromPhpBBCookies($request);
        if( empty($user) ) {
            throw new AuthenticationException("No login data from phpBB cookies");
        }

        $userIdentifier = $user->getUserIdentifier();
        $badge = new UserBadge($userIdentifier, function() use($user) {
           return $user;
        });

        return new SelfValidatingPassport($badge);
    }


    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName) : ?Response
    {
        // on success, let the request continue
        return null;
    }


    public function onAuthenticationFailure(Request $request, AuthenticationException $exception) : ?Response
    {
        $currentUser = $this->security->getUser();
        if( !empty($currentUser) ) {
            $this->security->logout(false);
        }

        // let the request continue
        return null;
    }


    public static function getSubscribedEvents() : array { return [ LogoutEvent::class => 'removeAllCookies']; }

    public function removeAllCookies() : static
    {
        $txtCookies = $_SERVER['HTTP_COOKIE'] ?? null;
        if( empty($txtCookies) ) {
            return $this;
        }

        $thePast = time() - 1000;

        $arrCookies = explode(';', $txtCookies);
        foreach($arrCookies as $cookie) {

            $parts  = explode('=', $cookie);
            $name   = trim($parts[0]);

            setcookie($name, '', $thePast);
            setcookie($name, '', $thePast, '/');
        }

        return $this;
    }
}
