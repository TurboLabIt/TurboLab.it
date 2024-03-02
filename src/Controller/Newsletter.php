<?php
namespace App\Controller;

use App\Exception\UserNotFoundException;
use App\Service\Newsletter as NewsletterService;
use App\Service\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TurboLabIt\Encryptor\EncryptionException;
use TurboLabIt\Encryptor\Encryptor;


class Newsletter extends BaseController
{
    const ERROR_BAD_ACCESS_KEY      = 1;
    const ERROR_USER_NOT_FOUND      = 3;
    const ERROR_USER_NOT_SUBSCRIBED = 5;
    const ERROR_USER_IS_SUBSCRIBED  = 7;


    #[Route('/newsletter/anteprima', name: 'app_newsletter_preview')]
    public function preview(NewsletterService $newsletter) : Response
    {
        $arrTestRecipients =
            $newsletter
                ->loadContent()
                ->loadTestRecipients()
                ->getRecipients();

        $recipient      = reset($arrTestRecipients);
        $username       = $recipient->getUsername();
        $userEmail      = $recipient->getEmail();
        $unsubscribeUrl = $recipient->getNewsletterUnsubscribeUrl();

        $email =
            $newsletter
                ->buildForOne($username, $userEmail, $unsubscribeUrl)
                ->getEmail();

        return $this->render( $email->getHtmlTemplate(), $email->getContext() );
    }


    #[Route('/newsletter/disiscrizione/{encryptedSubscriberData}', name: 'app_newsletter_unsubscribe')]
    public function unsubscribe(string $encryptedSubscriberData, Encryptor $encryptor, User $user, EntityManagerInterface $entityManager) : Response
    {
        try {
            $arrDecodedSubscriberData = $encryptor->decrypt($encryptedSubscriberData);

        } catch(EncryptionException) {
            return $this->unsubscribeErrorResponse(static::ERROR_BAD_ACCESS_KEY);
        }

        $userId = $arrDecodedSubscriberData["userId"];

        try {
            $user->load($userId);
        } catch(UserNotFoundException) {
            return $this->unsubscribeErrorResponse(static::ERROR_USER_NOT_FOUND, $arrDecodedSubscriberData);
        }

        if( !$user->isSubscribedToNewsletter() ) {
            return $this->unsubscribeErrorResponse(static::ERROR_USER_NOT_SUBSCRIBED, $arrDecodedSubscriberData, $user);
        }

        $user->unsubscribeFromNewsletter();
        $entityManager->flush();

        if( $userId == 2 ) {

            $user->subscribeToNewsletter();
            //$entityManager->flush();
        }

        return $this->render('newsletter/unsubscribe.html.twig', [
            "User"  => $user
        ]);
    }


    protected function unsubscribeErrorResponse(string $errorConstant, ?array $arrDecodedSubscriberData = null, ?User $user = null) : Response
    {
        return
            $this->render('newsletter/unsubscribe.html.twig', [
                "error"             => $errorConstant,
                "SubscriberData"    => $arrDecodedSubscriberData,
                "User"              => $user
            ], new Response(null, Response::HTTP_BAD_REQUEST));
    }


    #[Route('/newsletter/iscrizione/{encryptedSubscriberData}', name: 'app_newsletter_subscribe')]
    public function subscribe(string $encryptedSubscriberData, Encryptor $encryptor, User $user, EntityManagerInterface $entityManager) : Response
    {
        try {
            $arrDecodedSubscriberData = $encryptor->decrypt($encryptedSubscriberData);

        } catch(EncryptionException) {
            return $this->subscribeErrorResponse(static::ERROR_BAD_ACCESS_KEY);
        }

        $userId = $arrDecodedSubscriberData["userId"];

        try {
            $user->load($userId);
        } catch(UserNotFoundException) {
            return $this->subscribeErrorResponse(static::ERROR_USER_NOT_FOUND, $arrDecodedSubscriberData);
        }

        if( $userId == 2 ) {

            $user->unsubscribeFromNewsletter();
            $entityManager->flush();
        }

        if( $user->isSubscribedToNewsletter() ) {
            return $this->subscribeErrorResponse(static::ERROR_USER_IS_SUBSCRIBED, $arrDecodedSubscriberData, $user);
        }

        $user->subscribeToNewsletter();
        $entityManager->flush();

        return $this->render('newsletter/subscribe.html.twig', [
            "User" => $user
        ]);
    }


    protected function subscribeErrorResponse(string $errorConstant, ?array $arrDecodedSubscriberData = null, ?User $user = null) : Response
    {
        return
            $this->render('newsletter/subscribe.html.twig', [
                "error"             => $errorConstant,
                "SubscriberData"    => $arrDecodedSubscriberData,
                "User"              => $user
            ], new Response(null, Response::HTTP_BAD_REQUEST));
    }
}
