<?php
namespace App\Tests\Email;

use App\Service\Cms\Article;
use App\Service\User;
use App\Tests\BaseT;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;


abstract class EmailBaseT extends BaseT
{
    protected function expectArticleNotificationFrom(User $currentUser, TemplatedEmail $email) : static
    {
        $actualFrom = $email->getFrom();
        $this->assertEquals(1, count($actualFrom));
        $actualFrom = reset($actualFrom);

        $this->assertEqualsIgnoringCase( $currentUser->getUsername(), $actualFrom->getName() );
        $this->assertEqualsIgnoringCase( $this->getUser(User::ID_DEFAULT_ADMIN)->getEmail(), $actualFrom->getAddress() );

        return $this;
    }


    protected function expectArticleNotificationTo(array $arrExpectedRecipients, User $currentUser, TemplatedEmail $email) : static
    {
        // User::ID_DEFAULT_ADMIN is both author and the user publishing the content --> he must not get the "to" email
        unset($arrExpectedRecipients[ $currentUser->getId() ]);

        $arrActualRecipients = $email->getTo();

        $this->assertEquals( count($arrExpectedRecipients), count($arrActualRecipients) );
        $numOk = 0;

        /** @var User $user */
        foreach($arrExpectedRecipients as $user) {

            $userName   = $user->getUsername();
            $userEmail  = $user->getEmail();

            /** @var Address $recipient */
            foreach($arrActualRecipients as $key => $recipient) {

                $actualName     = $recipient->getName();
                $actualEmail    = $recipient->getAddress();

                if( $userName == $actualName && $userEmail == $actualEmail) {

                    $numOk++;
                    break;
                }
            }
        }

        $this->assertEquals( count($arrExpectedRecipients), $numOk );

        return $this;
    }


    protected function expectArticleNotificationCC(User $currentUser, TemplatedEmail $email) : static
    {
        $arrCC = $email->getCc();
        $this->assertEquals( count($arrCC), 1 );
        $ccAddress = reset($arrCC);

        $this->assertEqualsIgnoringCase( $currentUser->getUsername(), $ccAddress->getName() );
        $this->assertEqualsIgnoringCase( $currentUser->getEmail(), $ccAddress->getAddress() );

        return $this;
    }


    protected function expectArticleNotificationSubject(Article $article, TemplatedEmail $email) : static
    {
        $this->assertNoEntities($email->getSubject());
        $articleTitleNoSpecialChars = html_entity_decode($article->getTitle(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsStringIgnoringCase($articleTitleNoSpecialChars, $email->getSubject() );

        return $this;
    }


    protected function expectArticleNotificationPluralText(string $html) : static
    {
        $this->assertStringContainsStringIgnoringCase(' vi informo ', $html);
        $this->assertStringNotContainsStringIgnoringCase(' ti ', $html);

        return $this;
    }


    protected function expectArticleNotificationSingularText(string $html) : static
    {
        $this->assertStringContainsStringIgnoringCase(' ti informo ', $html);
        $this->assertStringNotContainsStringIgnoringCase(' vi ', $html);

        return $this;
    }


    protected function expectArticleNotificationReplyOnForumWarning(string $html) : static
    {
        $this->assertStringContainsStringIgnoringCase('non rispondere a questa email', $html);
        $this->assertStringContainsStringIgnoringCase('scrivi sul forum', $html);

        return $this;
    }
}
