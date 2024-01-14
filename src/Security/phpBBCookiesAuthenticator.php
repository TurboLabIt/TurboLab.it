<?php
namespace App\Security;

use App\Entity\PhpBB\User;
use App\Exception\PhpBBCookesAuthenticationException;
use App\Repository\PhpBB\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LogoutEvent;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/tree/main/docs/users.md
 */
class phpBBCookiesAuthenticator extends AbstractAuthenticator implements EventSubscriberInterface
{
    const COOKIE_BASENAME_PHPBB = 'turbocookie_2021_';


    public function __construct(protected Security $security, protected UserRepository $userRepository)
    { }


    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        $route = $request->attributes->get('_route');
        $supported = in_array($route, [
            //'app_home', 'app_home_num',
            //'app_news',
            //'app_tag', 'app_user',
            //'app_search_serp',
            'app_article',
            //'app_view_finiti', 'app_view_visitati', 'app_view_commentati', 'app_view_bozze',
            //'app_logout',
            //'app_admin_newsletter_unsubscribe', 'app_admin_newsletter_subscribe',
            //'app_test_index', 'app_test_zane',
        ]);

        return $supported;
    }


    protected function getUserFromPhpBBCookies(Request $request): ?User
    {
        $arrLoginData = [];
        foreach(["sid", "u", 'k'] as $oneCookieName) {

            $value = trim( $request->cookies->get(self::COOKIE_BASENAME_PHPBB . $oneCookieName) );
            if( empty($value) || $value == 1 ) {
                return null;
            }

            $arrLoginData[$oneCookieName] = $value;
        }

        if(empty($arrLoginData)) {
            return null;
        }

        $user = $this->userRepository->findOneByPhpBBCookiesValues($arrLoginData["u"], $arrLoginData["sid"], $arrLoginData["k"]);
        if( empty($user) ) {
            return null;
        }

        return $user;
    }


    public function authenticate(Request $request): Passport
    {
        /**
         * re-using symfony current user doesn't work bc
         * it wouldn't detect a logout started from phpBB
         * $user = $this->security->getUser();
         */

        if( empty($user) ) {

            $user = $this->getUserFromPhpBBCookies($request);
            if( empty($user) ) {
                throw new AuthenticationException("No login data from phpBB cookies");
            }
        }

        $userIdentifier = $user->getUserIdentifier();
        $badge = new UserBadge($userIdentifier, function() use($user) {
           return $user;
        });

        return new SelfValidatingPassport($badge);
    }


    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }


    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $currentUser = $this->security->getUser();
        if( !empty($currentUser) ) {
            $this->security->logout(false);
        }

        // let the request continue
        return null;
    }


    public static function getSubscribedEvents() : array
    {
        return [
            LogoutEvent::class => 'removeAllCookies',
        ];
    }


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
