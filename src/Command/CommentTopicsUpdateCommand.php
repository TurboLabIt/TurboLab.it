<?php
namespace App\Command;

use App\Service\Cms\Article;
use App\Service\Entity\Article as ArticleEntity;
use App\Service\Cms\ArticleEditor;
use App\Service\PhpBB\Topic;
use App\Service\User;
use App\ServiceCollection\Cms\ArticleEditorCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use Twig\Environment;


/**
 * 📚
 */
#[AsCommand(name: 'CommentTopicsUpdate', description: 'Update the opening post in each article\'s comment topic')]
class CommentTopicsUpdateCommand extends AbstractBaseCommand
{
    protected string $endpoint;
    protected array $arrResults = [];

    public function __construct(
        protected ArticleEditorCollection $articles, protected HttpClientInterface $httpClient,
        protected UrlGeneratorInterface $urlGenerator, protected Environment $twig,
        protected EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this->fxTitle('🛣️ Building the endpoint...');
        $this->endpoint =
            $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) .
                'comments-topic-update/';

        $this->fxOK("##" . $this->endpoint . "##");

        $this->fxTitle("🚚 Loading articles needing the update...");
        $this->articles->loadCommentsTopicNeedsUpdate();
        $count = $this->articles->count();
        $this->fxOK("##$count## article(s) loaded");

        if($count == 0) { return $this->endWithSuccess(); }

        $this->fxTitle("🔃 Updating...");
        $this->processItems($this->articles, [$this, 'processOneArticle']);

        $this->fxTitle("📊 Results");
        (new Table($output))
            ->setHeaders(['Done', 'Art. ID', 'URL', 'Message'])
            ->setRows($this->arrResults)
            ->render();

        $this->io->newLine();

        return $this->endWithSuccess();
    }


    protected function processOneArticle($key, ArticleEditor $article) : static
    {
        $arrResult = [];

        $authors            = $article->getAuthors();
        $firstAuthor        = reset($authors);
        $arrOtherAuthorIds  = [];

        foreach($authors as $author) {

            $authorId = $author->getId();

            if( $authorId == $firstAuthor->getId() ) {
                continue;
            }

            $arrOtherAuthorIds[] = $authorId;
        }

        $response =
            $this->httpClient->request(Request::METHOD_POST, $this->endpoint, [
                'verify_peer' => false,
                'verify_host' => false,
                'body' => [
                    'post-title'        => Topic::buildCommentsTitle( $article->getTitle() ),
                    'post-body'         => $this->twig->render('article/comments-topic.bbcode.twig', ['Article' => $article]),
                    'author-id'         => empty($firstAuthor) ? User::ID_SYSTEM : $firstAuthor->getId(),
                    'other-author-ids'  => $arrOtherAuthorIds,
                    'topic-id'          => $article->getCommentsTopic()->getId()
                ],
            ]);

        try {

            $message = $response->getContent();
            $arrResult[] = '✅';
            $article->setCommentsTopicNeedsUpdate(Article::COMMENTS_TOPIC_NEEDS_UPDATE_NO);
            $this->entityManager->flush();

        } catch(\Exception $ex) {

            $arrResult[] = '❌';

            $message = $response->getContent(false);

            if( !empty($message) ) { $message .= " 🦠 "; }

            $message .= $ex->getMessage();
        }

        $arrResult[] = $article->getId();
        $arrResult[] = $article->getShortUrl();
        $arrResult[] = $message;

        $this->arrResults[] = $arrResult;

        return $this;
    }
}
