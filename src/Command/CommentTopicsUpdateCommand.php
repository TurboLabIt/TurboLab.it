<?php
namespace App\Command;

use App\Service\Cms\Article;
use App\Service\Cms\ArticleUrlGenerator;
use App\Service\Entity\Article as ArticleEntity;
use App\Service\Cms\ArticleEditor;
use App\Service\PhpBB\Topic;
use App\Service\User;
use App\ServiceCollection\Cms\ArticleEditorCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
        protected EntityManagerInterface $entityManager, protected ArticleUrlGenerator $articleUrlGenerator,
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

        $this->fxTitle("🔃 Updating...");
        if( $count > 0 ) {

            $this->processItems($this->articles, [$this, 'updateCommentTopic']);

        } else {

            $this->fxOK("No articles need updating found.");
        }


        $this->fxTitle("🔎 Searching for articles referencing orphan comment topics...");

        $arrOrphans =
            $this->entityManager->createQuery(
                'SELECT t.id AS articleId, IDENTITY(t.commentsTopic) AS orphanTopicId
                FROM ' . Article::ENTITY_CLASS . ' t
                LEFT JOIN t.commentsTopic ct
                WHERE t.commentsTopic IS NOT NULL
                    AND ct.id IS NULL'
            )->getArrayResult();

        $orphanCount = count($arrOrphans);
        $this->fxOK("##$orphanCount## article(s) with orphan comment topic reference(s)");

        $this->fxTitle("🧹 Nulling out orphan references...");
        if( $orphanCount > 0 ) {

                $this->entityManager->createQuery(
                    'UPDATE ' . Article::ENTITY_CLASS . ' t
                    SET t.commentsTopic = NULL
                    WHERE t.id IN (:ids)'
                )
                    ->setParameter('ids', array_column($arrOrphans, 'articleId'))
                    ->execute();

            foreach($arrOrphans as $row) {
                $this->arrResults[] = [
                    '✅', 'set-null', $row['articleId'],
                    $this->articleUrlGenerator->generateShortUrlFromId($row['articleId']),
                    "Ref. to comment topic #" . $row['orphanTopicId'] . " removed"
                ];
            }

        } else {

            $this->fxOK("No articles need updating found.");
        }


        $this->fxTitle("📊 Results");
        (new Table($output))
            ->setHeaders(['Done', 'Op', 'Art. ID', 'URL', 'Message'])
            ->setRows($this->arrResults)
            ->render();

        $this->io->newLine();

        return $this->endWithSuccess();
    }


    protected function updateCommentTopic($key, ArticleEditor $article) : static
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

        $topicTitle = Topic::buildCommentsTitle( $article->getTitle() );
        $topicTitleEncoded = Topic::encodeTextAsTitle($topicTitle);

        $response =
            $this->httpClient->request(Request::METHOD_POST, $this->endpoint, [
                'verify_peer' => false,
                'verify_host' => false,
                'body' => [
                    'post-title'        => $topicTitleEncoded,
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

        } catch(Exception $ex) {

            $arrResult[] = '❌';

            $message = $response->getContent(false);

            if( !empty($message) ) { $message .= " 🦠 "; }

            $message .= $ex->getMessage();
        }

        $this->arrResults[] = array_merge($arrResult, [
            'topic-update', $article->getId(), $article->getShortUrl(), $message
        ]);

        return $this;
    }
}
