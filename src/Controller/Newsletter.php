<?php
namespace App\Controller;

use App\Service\Newsletter as NewsletterService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TurboLabIt\Encryptor\Encryptor;


class Newsletter extends BaseController
{
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
    public function unsubscribe(string $encryptedSubscriberData, Encryptor $encryptor) : Response
    {
        $arrSubscriberData  = $encryptor->decrypt($encryptedSubscriberData);
        if( empty($arrSubscriberData["email"]) || empty($arrSubscriberData["userId"]) ) {
            throw new InvalidNewsletterActionException();
        }


    }
}
