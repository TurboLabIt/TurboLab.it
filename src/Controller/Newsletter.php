<?php
namespace App\Controller;

use App\Exception\UserNotFoundException;
use App\Service\Cms\Article;
use App\Service\Newsletter as NewsletterService;
use App\Service\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TurboLabIt\Encryptor\EncryptionException;
use TurboLabIt\Encryptor\Encryptor;


class Newsletter extends BaseController
{
    const int ERROR_BAD_ACCESS_KEY      = 1;
    const int ERROR_USER_NOT_FOUND      = 3;
    const int ERROR_USER_NOT_SUBSCRIBED = 5;
    const int ERROR_USER_IS_SUBSCRIBED  = 7;


    public function __construct(
        protected Encryptor $encryptor, protected User $user, protected EntityManagerInterface $entityManager
    )
    {}


    #[Route('/newsletter', name: 'app_newsletter')]
    public function index(Article $article) : Response
    {
        // 👀 https://turbolab.it/402
        $url = $article->load(402)->getUrl();
        return $this->redirect($url);
    }


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
    public function unsubscribe(string $encryptedSubscriberData) : Response
    {
        try {
            $arrDecodedSubscriberData = $this->encryptor->decrypt($encryptedSubscriberData);

        } catch(EncryptionException) {
            return $this->unsubscribeErrorResponse(static::ERROR_BAD_ACCESS_KEY);
        }

        $userId = $arrDecodedSubscriberData["userId"];

        try {
            $this->user->load($userId);
        } catch(UserNotFoundException) {
            return $this->unsubscribeErrorResponse(static::ERROR_USER_NOT_FOUND, $arrDecodedSubscriberData);
        }

        if( !$this->user->isSubscribedToNewsletter() ) {
            return $this->unsubscribeErrorResponse(static::ERROR_USER_NOT_SUBSCRIBED, $arrDecodedSubscriberData);
        }

        $this->user->unsubscribeFromNewsletter();
        $this->entityManager->flush();

        if( $userId == 2 ) {

            $this->user->subscribeToNewsletter();
            $this->entityManager->flush();
        }

        return $this->render('newsletter/unsubscribe.html.twig', [
            "User"  => $this->user
        ]);
    }


    protected function unsubscribeErrorResponse(int $errorConstant, ?array $arrDecodedSubscriberData = null) : Response
    {
        return
            $this->render('newsletter/unsubscribe.html.twig', [
                "error"             => $errorConstant,
                "SubscriberData"    => $arrDecodedSubscriberData,
                "User"              => $this->user
            ], new Response(null, Response::HTTP_BAD_REQUEST));
    }


    #[Route('/newsletter/iscrizione/{encryptedSubscriberData}', name: 'app_newsletter_subscribe')]
    public function subscribe(string $encryptedSubscriberData) : Response
    {
        try {
            $arrDecodedSubscriberData = $this->encryptor->decrypt($encryptedSubscriberData);

        } catch(EncryptionException) {
            return $this->subscribeErrorResponse(static::ERROR_BAD_ACCESS_KEY);
        }

        $userId = $arrDecodedSubscriberData["userId"];

        try {
            $this->user->load($userId);
        } catch(UserNotFoundException) {
            return $this->subscribeErrorResponse(static::ERROR_USER_NOT_FOUND, $arrDecodedSubscriberData);
        }

        if( $userId == 2 ) {

            $this->user->unsubscribeFromNewsletter();
            $this->entityManager->flush();
        }

        if( $this->user->isSubscribedToNewsletter() ) {
            return $this->subscribeErrorResponse(static::ERROR_USER_IS_SUBSCRIBED, $arrDecodedSubscriberData);
        }

        $this->user->subscribeToNewsletter();
        $this->entityManager->flush();

        return $this->render('newsletter/subscribe.html.twig', [
            "User" => $this->user
        ]);
    }


    protected function subscribeErrorResponse(int $errorConstant, ?array $arrDecodedSubscriberData = null) : Response
    {
        return
            $this->render('newsletter/subscribe.html.twig', [
                "error"             => $errorConstant,
                "SubscriberData"    => $arrDecodedSubscriberData,
                "User"              => $this->user
            ], new Response(null, Response::HTTP_BAD_REQUEST));
    }
}