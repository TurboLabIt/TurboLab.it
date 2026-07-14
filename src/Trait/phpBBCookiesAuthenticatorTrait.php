<?php
namespace App\Trait;

use Exception;
use TurboLabIt\Encryptor\Encryptor;


trait phpBBCookiesAuthenticatorTrait
{
    const string COOKIE_BASENAME_PHPBB                          = 'turbocookie_2021_';
    const string COOKIE_NO_REMEMBER_ME_WORKAROUND               = 'tli-login-no-remember-me-workaround';
    const string NO_REMEMBER_ME_WORKAROUND_KEY_FILENAME         = 'no-remember-me-cookie-secret';

    // these routes don't need the User object
    const array AUTH_IGNORED_ROUTES = ['app_newsletter', 'app_tag_legacy'];

    protected Encryptor $encryptor;


    //<editor-fold defaultstate="collapsed" desc="*** 🚀 Main methods ***">
    public function setNoRememberMeCookie(array $arrData) : static
    {
        $cookieSecret =
            $this
                ->primeNoRememberMeEncryptor()
                ->encryptNoRememberMeCookieData($arrData);

        setcookie(
            static::COOKIE_NO_REMEMBER_ME_WORKAROUND, $cookieSecret, [
                'expires'   => 0,       // expires at the end of the session
                'path'      => '/',
                'domain'    => null,    // "this domain only, no subdomains"
                'secure'    => true,
                'httponly'  => true,
                'samesite'  => 'Strict'
            ]
        );

        return $this;
    }


    public function getNoRememberMeCookie() : ?array
    {
        $arrCookieValue =
            $this
                ->primeNoRememberMeEncryptor()
                ->decryptNoRememberMeCookieData();

        foreach(["id", "session_id", "timestamp"] as $requiredKey) {

            if( empty($arrCookieValue[$requiredKey]) ) {
                return null;
            }
        }

        return $arrCookieValue;
    }


    public function removeNoRememberMeCookie() : static
    {
        if( empty($_COOKIE[static::COOKIE_NO_REMEMBER_ME_WORKAROUND]) ) {
           return $this;
        }

        unset($_COOKIE[static::COOKIE_NO_REMEMBER_ME_WORKAROUND]);
        setcookie( static::COOKIE_NO_REMEMBER_ME_WORKAROUND, '', time()-3600 );
        return $this;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 👷 Internal methods ***">
    protected function primeNoRememberMeEncryptor() : static
    {
        // CSPRNG key auto-provisioned at var/encryptor/<filename>.key (0600), generated once then read back.
        // The var path is derived from THIS file's location (dirname(__DIR__, 2)), so it never depends on the CWD.
        $encryptionKey = Encryptor::getSuperSecureKey(
            dirname(__DIR__, 2) . '/var',
            static::NO_REMEMBER_ME_WORKAROUND_KEY_FILENAME
        );

        $this->encryptor = new Encryptor($encryptionKey);

        return $this;
    }


    protected function encryptNoRememberMeCookieData($data) : string { return $this->encryptor->encrypt($data); }


    protected function decryptNoRememberMeCookieData() : ?array
    {
        try {
            return $this->encryptor->decrypt($_COOKIE[static::COOKIE_NO_REMEMBER_ME_WORKAROUND] ?? null);
        } catch(Exception) { return null; }
    }
    //</editor-fold>
}
