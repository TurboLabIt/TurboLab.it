<?php
namespace App\Service;

use App\Entity\PhpBB\Forum;
use App\Service\Cms\Article;
use App\Service\PhpBB\ForumUrlGenerator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;


class Mailer extends \TurboLabIt\BaseCommand\Service\Mailer
{

    public function __construct(
        array $arrConfig,
        protected ForumUrlGenerator $forumUrlGenerator,
        MailerInterface $mailer, ProjectDir $projectDir, protected ParameterBagInterface $parameters
    )
    {
        parent::__construct($mailer, $projectDir, $parameters, $arrConfig);
    }


    public function notifyNewAuthorAddedToArticle(Article $article, User $newAuthor, User $userWhoAdded) : static
    {

    }


    public function buildNewAuthorAddedToArticle(Article $article, User $userWhoPublished) : static
    {
        $this->setFrom(null, $userWhoPublished->getFullName());

        /** @var User $author */
        foreach($article->getAuthors() as $author) {

            if( $author->getId() == $userWhoPublished->getId() ) {
                continue;
            }

            $arrTo[] = [
                "name"      => $author->getUsername(),
                "address"   => $author->getEmail(),
            ];
        }

        if( empty($arrTo) ) {
            return $this;
        }

        $this->addCc([[
            "name"      => $userWhoPublished->getUsername(),
            "address"   => $userWhoPublished->getEmail(),
        ]]);

        $arrTemplateParams = [
            "Recipients"            => $arrTo,
            "Article"               => $article,
            "UserWhoPublished"      => $userWhoPublished,
            "contactViaForumUrl"    => $this->forumUrlGenerator->generateTopicNewUrlFromForumId(Forum::ID_TLI)
        ];

        return
            $this
                ->build(
                    "Articolo pubblicato! " . $article->getTitle(),
                    "email/article-published.html.twig", $arrTemplateParams, $arrTo
                );
    }
}
