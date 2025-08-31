<?php
namespace App\Tests\Email;

use App\Service\User;


class ArticlePublishEmailTest extends EmailBaseT
{
    public function testEmailArticlePublishedMultipleAuthors()
    {
        $article        = $this->getArticle();
        $currentUser    = $this->getUser();
        $mailer         = $this->getMailer();

        $email =
            $mailer
                ->buildArticlePublished($article, $currentUser)
                ->getEmail();

        $this
            ->expectArticleNotificationFrom($currentUser, $email)
            ->expectArticleNotificationTo($article->getAuthors(), $currentUser, $email)
            ->expectArticleNotificationCC($currentUser, $email)
            ->expectArticleNotificationSubject($article, $email);

        $html = $this->getTwig()->render( $email->getHtmlTemplate(), $email->getContext() );

        $this
            ->expectArticleNotificationPluralText($html)
            ->expectArticleNotificationReplyOnForumWarning($html);

        $this->assertStringContainsStringIgnoringCase('TLI Dev Libero.it', $html);
        $this->assertStringContainsStringIgnoringCase('TLI Dev Outlook', $html);

        $this->assertStringContainsStringIgnoringCase('siete autori', $html);
        $this->assertStringNotContainsStringIgnoringCase(' autore ', $html);

        $this->assertStringContainsStringIgnoringCase($article->getTitle(), $html);

        $this->assertStringContainsStringIgnoringCase($currentUser->getUsername(), $html);

        $mailer
            ->block(false)
            ->send();
    }


    public function testEmailArticlePublishedSingleAuthor()
    {
        $article        = $this->getArticle(2983); // 👀 Uova di camoscio https://turbolab.it/2983
        $currentUser    = $this->getUser(User::ID_SYSTEM);
        $mailer         = $this->getMailer();

        $email =
            $mailer
                ->buildArticlePublished($article, $currentUser)
                ->getEmail();

        $this
            ->expectArticleNotificationFrom($currentUser, $email)
            ->expectArticleNotificationTo($article->getAuthors(), $currentUser, $email)
            ->expectArticleNotificationCC($currentUser, $email)
            ->expectArticleNotificationSubject($article, $email);

        $html = $this->getTwig()->render( $email->getHtmlTemplate(), $email->getContext() );

        $this
            ->expectArticleNotificationSingularText($html)
            ->expectArticleNotificationReplyOnForumWarning($html);

        $this->assertStringContainsStringIgnoringCase('frency', $html);

        $this->assertStringContainsStringIgnoringCase('sei autore', $html);
        $this->assertStringNotContainsStringIgnoringCase(' autori ', $html);

        $this->assertStringContainsStringIgnoringCase($article->getTitle(), $html);

        $this->assertStringContainsStringIgnoringCase($currentUser->getUsername(), $html);

        $mailer
            ->block(false)
            ->send();
    }
}
