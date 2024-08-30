<?php
namespace App\Controller;

use App\Exception\UserNotFoundException;
use App\Service\Cms\Article;
use App\Service\Cms\UrlGenerator;
use App\Service\FrontendHelper;
use App\Service\Newsletter as NewsletterService;
use App\Service\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TurboLabIt\Encryptor\EncryptionException;
use TurboLabIt\Encryptor\Encryptor;


/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/newsletter.md
 */
class NewsletterController extends BaseController
{
    const int ERROR_BAD_ACCESS_KEY      = 1;
    const int ERROR_USER_NOT_FOUND      = 3;
    const int ERROR_USER_NOT_SUBSCRIBED = 5;
    const int ERROR_USER_IS_SUBSCRIBED  = 7;


    public function __construct(
        protected Encryptor $encryptor, protected User $user, protected EntityManagerInterface $entityManager,
        RequestStack $requestStack, protected FrontendHelper $frontendHelper
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
    public function preview(
        NewsletterService $newsletter, User $currentUser, ParameterBagInterface $parameters
    ) : Response
    {
        $countArticles =
            $newsletter
                ->loadContent()
                ->loadRecipients()
                ->countArticles();

        if( $countArticles == 0 && $parameters->get('kernel.environment') != 'prod' ) {
            $newsletter->loadTestArticles();
        }

        $countTopics = $newsletter->countTopics();
        if( $countTopics == 0 && $parameters->get('kernel.environment') != 'prod' ) {
            $newsletter->loadTestTopics();
        }

        /** @var ?\App\Entity\PhpBB\User $entityCurrentUser */
        $entityCurrentUser = $this->getUser();
        $userId = $entityCurrentUser?->getId() ?? User::SYSTEM_USER_ID;
        $user   = $currentUser->load($userId);

        $email =
            $newsletter
                ->buildForOne($user)
                ->getEmail();

        return $this->render( $email->getHtmlTemplate(), $email->getContext() );
    }


    #[Route('/newsletter/open', name: 'app_newsletter_opener')]
    public function opener(NewsletterService $newsletter) : Response
    {
        $goToUrl        = $this->request->get("url");
        $arrParsedUrl   = parse_url($goToUrl);
        // prevent open redirection
        if( !in_array($arrParsedUrl["host"], UrlGenerator::INTERNAL_DOMAINS) ) {
            throw new Exception("Bad redirection hostname");
        }

        $encryptedUserData = $this->request->get("opener");

        try {
            $arrUserData = $this->encryptor->decrypt($encryptedUserData);
            if( $arrUserData["scope"] != 'newsletterOpenerUrl' ) {
                throw new Exception("Pretty Try (For a White Guy) | Invalid scope");
            }

            $newsletter->confirmOpener($arrUserData["userId"]);

        } catch(Exception) {}

        return $this->redirect($goToUrl);
    }


    #[Route('/newsletter/disiscrizione/{encryptedSubscriberData}', name: 'app_newsletter_unsubscribe')]
    public function unsubscribe(NewsletterService $newsletter, string $encryptedSubscriberData) : Response
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

        $newsletter->unsubscribeUser($this->user);

        return $this->render('newsletter/unsubscribe.html.twig', [
            "activeMenu"        => 'newsletter',
            "FrontendHelper"    => $this->frontendHelper,
            "User"              => $this->user
        ]);
    }


    protected function unsubscribeErrorResponse(int $errorConstant, ?array $arrDecodedSubscriberData = null) : Response
    {
        return
            $this->render('newsletter/unsubscribe.html.twig', [
                "activeMenu"        => 'newsletter',
                "FrontendHelper"    => $this->frontendHelper,
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
            "activeMenu"        => 'newsletter',
            "FrontendHelper"    => $this->frontendHelper,
            "User"              => $this->user
        ]);
    }


    protected function subscribeErrorResponse(int $errorConstant, ?array $arrDecodedSubscriberData = null) : Response
    {
        return
            $this->render('newsletter/subscribe.html.twig', [
                "activeMenu"        => 'newsletter',
                "FrontendHelper"    => $this->frontendHelper,
                "error"             => $errorConstant,
                "SubscriberData"    => $arrDecodedSubscriberData,
                "User"              => $this->user
            ], new Response(null, Response::HTTP_BAD_REQUEST));
    }
}
