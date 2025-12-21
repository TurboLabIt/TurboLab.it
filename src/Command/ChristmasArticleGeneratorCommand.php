<?php
namespace App\Command;

use App\Repository\PhpBB\PostRepository;
use App\Repository\PhpBB\TopicRepository;
use App\Repository\PhpBB\UserRepository;
use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Cms\Image;
use App\Service\Cms\Tag;
use App\Service\Factory;
use App\Service\Newsletter;
use App\Service\User;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\ArticleEditorCollection;
use App\ServiceCollection\UserCollection;
use DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use Twig\Environment;


#[AsCommand(name: 'ChristmasArticleGenerator', description: 'Generate and send the weekly newsletter')]
class ChristmasArticleGeneratorCommand extends AbstractBaseCommand
{
    const string ARTICLE_TITLE = "Auguri di buone feste da TLI";

    protected bool $allowDryRunOpt = true;

    protected ArticleCollection $articlesNew;
    protected ArticleCollection $newsNew;
    protected ArticleCollection $articlesTli;
    protected ArticleCollection $articlesWindows;
    protected ArticleCollection $articlesLinux;
    protected ArticleCollection $articlesAndroid;
    protected ArticleCollection $articlesHardware;

    protected UserCollection $usersNew;
    protected UserCollection $usersTopPosters;
    protected UserCollection $authors;

    protected Article $articleNewsletter;
    protected Article $articleJoinTli;

    protected ArticleEditor $articleEditor;


    public function __construct(
        protected Factory $factory, protected ArticleEditorCollection $articlesEditor,
        protected PostRepository $forumPostRepository, protected TopicRepository $forumTopicRepository,
        protected UserRepository $userRepository,
        protected Environment $twig, protected User $articleAuthor, protected Tag $tagTli,
        protected ArticleEditorCollection $articleEditorCollection, protected Image $spotlight,
        protected Newsletter $newsletter
    )
    {
        $this->articlesNew      = $factory->createArticleCollection();
        $this->newsNew          = $factory->createArticleCollection();
        $this->articlesTli      = $factory->createArticleCollection();
        $this->articlesWindows  = $factory->createArticleCollection();
        $this->articlesLinux    = $factory->createArticleCollection();
        $this->articlesAndroid  = $factory->createArticleCollection();
        $this->articlesHardware = $factory->createArticleCollection();

        $this->usersNew         = $factory->createUserCollection();
        $this->usersTopPosters  = $factory->createUserCollection();
        $this->authors          = $factory->createUserCollection();

        $this->articleNewsletter= $factory->createArticle();
        $this->articleJoinTli   = $factory->createArticle();

        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this
            ->fxTitle("Loading contents...")
            ->loadContent()

            ->fxTitle("Preparing web article...")
            ->prepareWebArticle();

        return $this->endWithSuccess();
    }


    protected function loadContent() : static
    {
        $this->articlesNew->loadNewOfTheYear(Article::FORMAT_ARTICLE);
        $countArticles = $this->articlesNew->count();
        $this->fxOK("📖 $countArticles article(s) loaded");

        $this->newsNew->loadNewOfTheYear(Article::FORMAT_NEWS);
        $countNews = $this->newsNew->count();
        $this->fxOK("📰 $countNews news loaded");

        $this->fxOK("➕ " . $countArticles + $countNews . " total contents");

        $this->usersNew->loadNewOfTheYear();
        $countUsers = $this->usersNew->count();
        $this->fxOK("👥 $countUsers new user(s) loaded");

        $this->usersTopPosters->loadTopPosterOfTheYear();
        $countUsers = $this->usersTopPosters->count();
        $this->fxOK("👥 $countUsers top forum poster(s) loaded");

        $this->authors->loadTopAuthorsOfTheYear();
        $countUsers = $this->authors->count();
        $this->fxOK("👥 $countUsers authors(s) loaded");

        $this->tagTli->load(Tag::ID_TURBOLAB_IT);
        $this->fxOK("🏷️ Tag of the article loaded (" . $this->tagTli->getTitle() . ")");

        $this->articlesTli->loadNewOfTheYearWithTag($this->tagTli);
        $countArticles = $this->articlesTli->count();
        $this->fxOK("📁 $countArticles article(s) tagged #" . $this->tagTli->getTitle() . " loaded");

        $tagWindows = $this->factory->createTag()->load(Tag::ID_WINDOWS);
        $this->articlesWindows->loadNewOfTheYearWithTag($tagWindows, 5);
        $countArticles = $this->articlesWindows->count();
        $this->fxOK("📁 $countArticles article(s) tagged #" . $tagWindows->getTitle() . " loaded");

        $tagLinux = $this->factory->createTag()->load(Tag::ID_LINUX);
        $this->articlesLinux->loadNewOfTheYearWithTag($tagLinux, 5);
        $countArticles = $this->articlesLinux->count();
        $this->fxOK("📁 $countArticles article(s) tagged #" . $tagLinux->getTitle() . " loaded");

        $tagAndroid = $this->factory->createTag()->load(Tag::ID_ANDROID);
        $this->articlesAndroid->loadNewOfTheYearWithTag($tagAndroid, 5);
        $countArticles = $this->articlesAndroid->count();
        $this->fxOK("📁 $countArticles article(s) tagged #" . $tagAndroid->getTitle() . " loaded");

        $tagHardware = $this->factory->createTag()->load(Tag::ID_HARDWARE);
        $this->articlesHardware->loadNewOfTheYearWithTag($tagHardware, 5);
        $countArticles = $this->articlesHardware->count();
        $this->fxOK("📁 $countArticles article(s) tagged #" . $tagHardware->getTitle() . " loaded");

        $this->articleNewsletter->load(Article::ID_NEWSLETTER);
        $this->fxOK("📖️ Newsletter article loaded (" . $this->articleNewsletter->getTitle() . ")");

        $this->newsletter->loadRecipients();
        $this->fxOK("📧 Newsletter loaded (" . $this->newsletter->countRecipients() . ") subscriber(s)");

        $this->articleAuthor->load(User::ID_DEFAULT_ADMIN);
        $this->fxOK("🥸 Author of the article loaded (" . $this->articleAuthor->getUsername() . ")");

        $this->articleJoinTli->load(Article::ID_HOW_TO_JOIN);
        $this->fxOK("📖️ Newsletter article loaded (" . $this->articleNewsletter->getTitle() . ")");

        $this->spotlight->load(Image::ID_CHRISTMAS_SPOTLIGHT);
        $this->fxOK("🖼️ Christmas spotlight loaded (" . $this->spotlight->getUrl(Image::SIZE_REG) . ")");

        $this->output->writeln('');
        $this->articleEditor =
            $this->articleEditorCollection->loadExistingChristmas()->first() ??
            $this->factory->createArticleEditor();

        if( empty($this->articleEditor->getId()) ) {

            $this->fxInfo("Pre-existing article not found. Generating it now.");
            $this->articleEditor->createCommentsTopicPlaceholder();

        } else {

            $this->fxInfo("Pre-existing article found! Updating it.");
        }

        return $this;
    }


    protected function prepareWebArticle() : static
    {
        $contentsNewNum = $this->articlesNew->count() +  $this->newsNew->count();

        $articleBody =
            $this->twig->render('events/christmas.html.twig', [
                    'Spotlight'                 => $this->spotlight,
                    'contentsNewNum'            => $contentsNewNum,
                    'contentsNewWeeklyAvg'      => $contentsNewNum / 52,
                    'articlesNewNum'            => $this->articlesNew->count(),
                    'newsNewNum'                => $this->newsNew->count(),
                    'Authors'                   => $this->authors,
                    'forumPostsNewNum'          => $this->forumPostRepository->countNewOfTheYear(),
                    'currentYear'               => date('Y'),
                    'nextYear'                  => date('Y') + 1,
                    'usersNewNum'               => $this->usersNew->count(),
                    'usersTotalNum'             => $this->userRepository->countAllActive(),
                    'ForumNewUsersTopPosters'   => $this->usersNew->getTopPosters(),
                    'forumTopicsNewNum'         => $this->forumTopicRepository->countNewOfTheYear(),
                    'ForumUsersTopPosters'      => $this->usersTopPosters->getTopPosters(),
                    'ArticlesTli'               => $this->articlesTli,
                    'ArticlesWindows'           => $this->articlesWindows,
                    'ArticlesLinux'             => $this->articlesLinux,
                    'ArticlesAndroid'           => $this->articlesAndroid,
                    'ArticlesHardware'          => $this->articlesHardware,
                    'ArticleNewsletter'         => $this->articleNewsletter,
                    'Newsletter'                => $this->newsletter,
                    'ArticleJoinTli'            => $this->articleJoinTli
                ]
            );

        $this->articleEditor
            ->setTitle( static::ARTICLE_TITLE . " (edizione " . date('Y') . ")" )
            ->addAuthor($this->articleAuthor)
            ->addTag($this->tagTli, $this->articleAuthor)
            ->setFormat(Article::FORMAT_ARTICLE)
            ->setBody($articleBody)
            ->setPublishedAt( new DateTime(date('Y') . '-12-23 00:00:00') )
            ->setPublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED)
            ->excludeFromPeriodicUpdateList()
            ->save( $this->isNotDryRun(true) );

        return
            $this->isNotDryRun()
                ? $this->fxOK("Article saved: " . $this->articleEditor->getUrl())
                : $this->fxWarning('The web article was NOT saved');
    }
}
