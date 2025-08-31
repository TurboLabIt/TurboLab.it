<?php
namespace App\Tests\Email;

use App\Service\User;


class ArticleChangeAuthorsEmailTest extends EmailBaseT
{
    public function testAuthorsAdded()
    {
        $article        = $this->getArticleEditor();
        $currentUser    = $this->getUser();
        $mailer         = $this->getMailer();

        $arrPreviousAuthors = $article->getAuthors();

        $arrAddedAuthors = [
            $this->getUser()->loadByUsernameClean('frency'),
            $this->getUser()->loadByUsernameClean('crazy.cat')
        ];

        foreach($arrAddedAuthors as $author) {
            $article->addAuthor($author);
        }

        $email =
            $mailer
                ->buildArticleChangeAuthors($article, $currentUser, $arrPreviousAuthors)
                ->getEmail();

        $this
            ->expectArticleNotificationFrom($currentUser, $email)
            ->expectArticleNotificationTo($article->getAuthors(), $currentUser, $email)
            ->expectArticleNotificationCC($currentUser, $email)
            ->expectArticleNotificationSubject($article, $email);

        $html = $this->getTwig()->render( $email->getHtmlTemplate(), $email->getContext() );

        $this->assertStringContainsStringIgnoringCase(' frency, ', $html);
        $this->assertStringContainsStringIgnoringCase(' crazy.cat ', $html);

        $this
            ->expectArticleNotificationPluralText($html)
            ->expectArticleNotificationReplyOnForumWarning($html);

        $this->assertStringContainsStringIgnoringCase('modificato gli autori', $html);

        $this->assertStringContainsStringIgnoringCase($article->getTitle(), $html);

        $this->assertStringContainsStringIgnoringCase('autori aggiunti', $html);
        $this->assertStringNotContainsStringIgnoringCase('autori rimossi', $html);

        $this->assertStringContainsStringIgnoringCase('<li>frency</li>', $html);
        $this->assertStringContainsStringIgnoringCase('<li>crazy.cat</li>', $html);

        $this->assertStringContainsStringIgnoringCase($currentUser->getUsername(), $html);

        $mailer
            ->block(false)
            ->send();
    }


    public function testAuthorsRemoved()
    {
        $article        = $this->getArticleEditor();
        $currentUser    = $this->getUser();
        $mailer         = $this->getMailer();

        $arrPreviousAuthors = $article->getAuthors();
        $userSystem = $arrPreviousAuthors[User::ID_SYSTEM];
        $article->setAuthors([$userSystem], $currentUser);

        $email =
            $mailer
                ->buildArticleChangeAuthors($article, $currentUser, $arrPreviousAuthors)
                ->getEmail();

        $this
            ->expectArticleNotificationFrom($currentUser, $email)
            ->expectArticleNotificationTo($arrPreviousAuthors, $currentUser, $email)
            ->expectArticleNotificationCC($currentUser, $email)
            ->expectArticleNotificationSubject($article, $email);

        $html = $this->getTwig()->render( $email->getHtmlTemplate(), $email->getContext() );

        foreach($arrPreviousAuthors as $author) {
            $this->assertStringContainsStringIgnoringCase(' ' . $author->getUsername(), $html);
        }

        $this
            ->expectArticleNotificationPluralText($html)
            ->expectArticleNotificationReplyOnForumWarning($html);

        $this->assertStringContainsStringIgnoringCase('modificato gli autori', $html);

        $this->assertStringContainsStringIgnoringCase($article->getTitle(), $html);

        $this->assertStringContainsStringIgnoringCase('autori rimossi', $html);
        $this->assertStringNotContainsStringIgnoringCase('autori aggiunti', $html);

        foreach($arrPreviousAuthors as $userId => $author) {
            if( $userId != User::ID_SYSTEM ) {
                $this->assertStringContainsStringIgnoringCase('<li>' . $author->getUsername() . '</li>', $html);
            }
        }

        $this->assertStringContainsStringIgnoringCase($currentUser->getUsername(), $html);

        $mailer
            ->block(false)
            ->send();
    }


    public function testAuthorsAddedAndRemoved()
    {
        $article        = $this->getArticleEditor();
        $currentUser    = $this->getUser();
        $mailer         = $this->getMailer();

        $arrPreviousAuthors = $article->getAuthors();

        $arrAddedAuthors = [
            $arrPreviousAuthors[User::ID_SYSTEM],
            $this->getUser()->loadByUsernameClean('frency'),
            $this->getUser()->loadByUsernameClean('crazy.cat')
        ];

        $article->setAuthors($arrAddedAuthors, $currentUser);

        $arrAllRecipients = [];
        foreach(array_merge($arrPreviousAuthors, $arrAddedAuthors) as $author) {

            $userId = $author->getId();
            $arrAllRecipients[$userId] = $author;
        }

        $email =
            $mailer
                ->buildArticleChangeAuthors($article, $currentUser, $arrPreviousAuthors)
                ->getEmail();

        $this
            ->expectArticleNotificationFrom($currentUser, $email)
            ->expectArticleNotificationTo($arrAllRecipients, $currentUser, $email)
            ->expectArticleNotificationCC($currentUser, $email)
            ->expectArticleNotificationSubject($article, $email);

        $html = $this->getTwig()->render( $email->getHtmlTemplate(), $email->getContext() );

        foreach($arrAllRecipients as $author) {
            $this->assertStringContainsStringIgnoringCase(' ' . $author->getUsername(), $html);
        }

        $this
            ->expectArticleNotificationPluralText($html)
            ->expectArticleNotificationReplyOnForumWarning($html);

        $this->assertStringContainsStringIgnoringCase('modificato gli autori', $html);

        $this->assertStringContainsStringIgnoringCase($article->getTitle(), $html);

        $this->assertStringContainsStringIgnoringCase('autori aggiunti', $html);
        $this->assertStringContainsStringIgnoringCase('autori rimossi', $html);

        foreach($arrAllRecipients as $userId => $author) {
            if( $userId != User::ID_SYSTEM ) {
                $this->assertStringContainsStringIgnoringCase('<li>' . $author->getUsername() . '</li>', $html);
            }
        }

        $this->assertStringContainsStringIgnoringCase($currentUser->getUsername(), $html);

        $mailer
            ->block(false)
            ->send();
    }
}
