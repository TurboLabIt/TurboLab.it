<?php
namespace App\Trait;

use Exception;
use TurboLabIt\Encryptor\Encryptor;


trait phpBBCookiesAuthenticatorTrait
{
    const string COOKIE_BASENAME_PHPBB                          = 'turbocookie_2021_';
    const string COOKIE_NO_REMEMBER_ME_WORKAROUND               = 'tli-login-no-remember-me-workaround';
    const string NO_REMEMBER_ME_WORKAROUND_ENCRYPTOR_KEY_PATH   = 'var/no-remember-me-cookie-secret.key';

    protected Encryptor $encryptor;


    //<editor-fold defaultstate="collapsed" desc="*** 🚀 Main methods ***">
    public function setNoRememberMeCookie(array $arrData, string $outputDirectoryPathPrepend = '') : static
    {
        $cookieSecret =
            $this
                ->primeNoRememberMeEncryptor($outputDirectoryPathPrepend)
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


    public function getNoRememberMeCookie(string $outputDirectoryPathPrepend = '') : ?array
    {
        $arrCookieValue =
            $this
                ->primeNoRememberMeEncryptor($outputDirectoryPathPrepend)
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
    protected function primeNoRememberMeEncryptor(string $outputDirectoryPathPrepend = '') : static
    {
        $keyFilePath = $outputDirectoryPathPrepend . static::NO_REMEMBER_ME_WORKAROUND_ENCRYPTOR_KEY_PATH;

        if( !file_exists($keyFilePath) ) {

            $encryptionKey = md5(uniqid(rand(), true));
            file_put_contents($keyFilePath, $encryptionKey);

        } else {

            $encryptionKey = file_get_contents($keyFilePath);
        }

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
