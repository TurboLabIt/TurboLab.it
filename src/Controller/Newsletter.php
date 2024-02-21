<?php
namespace App\Controller;

use App\Service\Cms\Newsletter as NewsletterMailer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class Newsletter extends BaseController
{
    #[Route('/newsletter/anteprima', name: 'app_newsletter_preview')]
    public function preview(NewsletterMailer $mailer) : Response
    {
        $email =
            $mailer
                ->prepare()
                ->buildForOne("Nyan Cat", "info@turbolab.it")
                ->getEmail();

        return $this->render( $email->getHtmlTemplate(), $email->getContext() );
    }
}
