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


    public function buildArticleChangeAuthors(Article $article, User $userWhoDidIt, array $arrPreviousAuthors) : static
    {
        $this->setFrom(null, $userWhoDidIt->getUsername() );

        $arrCurrentAuthors = $article->getAuthors();

        $arrAuthorsAdded = [];
        foreach($arrCurrentAuthors as $authorId => $author) {

            if( $authorId != $userWhoDidIt->getId() ) {
                $arrTo[$authorId] = $author;
            }

            if( array_key_exists($authorId, $arrPreviousAuthors) ) {
                continue;
            }

            $arrAuthorsAdded[$authorId] = $author;
        }

        $arrAuthorsRemoved  = [];
        foreach($arrPreviousAuthors as $authorId => $author) {

            if( array_key_exists($authorId, $arrCurrentAuthors) ) {
                continue;
            }

            if( $authorId != $userWhoDidIt->getId() ) {
                $arrTo[$authorId] = $author;
            }

            $arrAuthorsRemoved[$authorId] = $author;
        }

        if( empty($arrTo) ) {
            return $this;
        }

        $this->addCc([[
            "name"      => $userWhoDidIt->getUsername(),
            "address"   => $userWhoDidIt->getEmail(),
        ]]);


        $arrTemplateParams = [
            "AuthorsAdded"      => $arrAuthorsAdded,
            "AuthorsRemoved"    => $arrAuthorsRemoved,
            "Article"           => $article,
            "UserWhoDidIt"      => $userWhoDidIt,
            "contactViaForumUrl"=> $this->forumUrlGenerator->generateTopicNewUrlFromForumId(Forum::ID_TLI)
        ];

        return
            $this
                ->build(
                    "Autori articolo modificati - " . $article->getTitle(),
                    "email/article-authors-change.html.twig", $arrTemplateParams, $arrTo
                );
    }


    public function buildArticlePublished(Article $article, User $userWhoDidIt) : static
    {
        $this->setFrom(null, $userWhoDidIt->getUsername() );

        $arrCurrentAuthors = $article->getAuthors();

        /** @var User $author */
        foreach($arrCurrentAuthors as $author) {

            if( $author->getId() == $userWhoDidIt->getId() ) {
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
            "name"      => $userWhoDidIt->getUsername(),
            "address"   => $userWhoDidIt->getEmail(),
        ]]);

        $arrTemplateParams = [
            "Article"           => $article,
            "UserWhoDidIt"      => $userWhoDidIt,
            "contactViaForumUrl"=> $this->forumUrlGenerator->generateTopicNewUrlFromForumId(Forum::ID_TLI)
        ];

        return
            $this
                ->build(
                    "Articolo pubblicato! " . $article->getTitle(),
                    "email/article-published.html.twig", $arrTemplateParams, $arrTo
                );
    }
}
