<?php
namespace App\Controller;

use App\Exception\UserNotFoundException;
use App\Service\Cms\Article;
use App\Service\Cms\UrlGenerator;
use App\Service\Newsletter as NewsletterService;
use App\Service\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TurboLabIt\Encryptor\EncryptionException;
use TurboLabIt\Encryptor\Encryptor;


/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/newsletter.md
 */
class Newsletter extends BaseController
{
    const int ERROR_BAD_ACCESS_KEY      = 1;
    const int ERROR_USER_NOT_FOUND      = 3;
    const int ERROR_USER_NOT_SUBSCRIBED = 5;
    const int ERROR_USER_IS_SUBSCRIBED  = 7;


    public function __construct(
        protected Encryptor $encryptor, protected User $user, protected EntityManagerInterface $entityManager,
        RequestStack $requestStack
    )
    {
        $this->request = $requestStack->getCurrentRequest();
    }


    #[Route('/newsletter', name: 'app_newsletter')]
    public function index(Article $article) : Response
    {
        $url = $article->load(Article::ID_NEWSLETTER)->getUrl();
        return $this->redirect($url);
    }


    #[Route('/newsletter/anteprima', name: 'app_newsletter_preview')]
    public function preview(NewsletterService $newsletter, User $currentUser) : Response
    {
        $newsletter
            ->loadContent()
            ->loadTestRecipients();

        $currentUserId = $this->getUser()?->getId();
        if( empty($currentUserId) ) {

            $arrTestRecipients = $newsletter->getRecipients();
            $user = reset($arrTestRecipients);

        } else {

            $user = $currentUser->load($currentUserId);
        }

        $email =
            $newsletter
                ->buildForOne($user)
                ->getEmail();

        return $this->render( $email->getHtmlTemplate(), $email->getContext() );
    }


    #[Route('/newsletter/open', name: 'app_newsletter_opener')]
    public function opener() : Response
    {
        $goToUrl        = $this->request->get("url");
        $arrParsedUrl   = parse_url($goToUrl);
        // prevent open redirection
        if( !in_array($arrParsedUrl["host"], UrlGenerator::INTERNAL_DOMAINS) ) {
            throw new \Exception("Bad redirection hostname");
        }

        $encryptedUserData = $this->request->get("opener");

        try {
            $arrUserData = $this->encryptor->decrypt($encryptedUserData);
            //$this->user->load($arrUserData["userId"])->setNewsletterOpened();
            //$this->entityManager->flush();

        } catch(\Exception) {}

        return $this->redirect($goToUrl);
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

        //
        if( $userId == User::SYSTEM_USER_ID ) {
            $this->user->subscribeToNewsletter();
        }

        if( !$this->user->isSubscribedToNewsletter() ) {
            return $this->unsubscribeErrorResponse(static::ERROR_USER_NOT_SUBSCRIBED, $arrDecodedSubscriberData);
        }

        $this->user->unsubscribeFromNewsletter();
        $this->entityManager->flush();

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

        //
        if( $userId == User::SYSTEM_USER_ID ) {
            $this->user->unsubscribeFromNewsletter();
        }


        if( $this->user->isSubscribedToNewsletter() ) {
            return $this->subscribeErrorResponse(static::ERROR_USER_IS_SUBSCRIBED, $arrDecodedSubscriberData);
        }

        $this->user->subscribeToNewsletter();

        //
        if( $userId == User::SYSTEM_USER_ID ) {
            $this->user->unsubscribeFromNewsletter();
        }

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
