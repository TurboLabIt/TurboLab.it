<?php
namespace App\Service;

use App\Entity\Bug;
use App\Entity\PhpBB\Forum;
use App\Entity\PhpBB\Post as PostEntity;
use App\Repository\BugRepository;
use App\Service\PhpBB\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class Issue
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected Post $post, protected GitHub $github,
        protected HttpClientInterface $httpClient, protected UrlGeneratorInterface $urlGenerator
    ) {}


    public function createFromForumPostId(int $postId, User $author, string $authorIpAddress) : Bug
    {
        if( empty($postId) || $postId < 1) {
            throw new BadRequestHttpException("Bad post ID");
        }

        /** @var PostEntity $postEntity */
        $postEntity = $this->entityManager->getRepository(PostEntity::class)->getOneById($postId);

        if( empty($postEntity) ) {
            throw new NotFoundHttpException("Post not found");
        }

        if( !in_array($postEntity->getForumId(), [Forum::ID_TLI, Forum::ID_COMMENTS]) ) {
            throw new AccessDeniedHttpException("Forum not allowed");
        }

        $this->post->setEntity($postEntity);

        $issueTitle = trim(html_entity_decode($this->post->getTitle(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // remove "Re: " (reply) from the beginning of the title
        $issueTitle = trim(preg_replace('/^re:\s*/i', '', $issueTitle));

        $issueBody  = trim('
            🗨️ [Post originale](' . $this->post->getUrl() . ')' . PHP_EOL . PHP_EOL .
            'Autore del post: ' . $this->post->getUser()?->getUsername() . " || " .
            'Issue creata da: ' . $author->getUsername()
        );

        $arrGitHubResponse = $this->github->createIssue($issueTitle, $issueBody);

        $remoteId   = $arrGitHubResponse["number"];
        $remoteUrl  = $arrGitHubResponse["html_url"];

        $bug =
            (new Bug())
                ->setRemoteId($remoteId)
                ->setRemoteUrl($remoteUrl)
                ->setPost($this->post->getEntity())
                ->setUser($author->getEntity())
                ->setUserIpAddress($authorIpAddress);

        $this->entityManager->persist($bug);
        $this->entityManager->flush();

        $response = $this->updatePost($bug);

        return $bug;
    }


    public function rateLimiting(User $author, string $authorIpAddress) : static
    {
        $bugByUser = $this->entityManager->getRepository(Bug::class)->getRecentByAuthor($author, $authorIpAddress);

        if( count($bugByUser) > BugRepository::TIME_LIMIT_BUGS ) {

            throw new TooManyRequestsHttpException(3600 * BugRepository::TIME_LIMIT_HOURS,
                "Stai aprendo troppe issue (tu, oppure qualcuno con il tuo stesso indirizzo IP)! Per favore, riprova più tardi."
            );
        }

        return $this;
    }


    protected function updatePost(Bug $bug) : string
    {
        $endpointUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'issue-add-to-post/';
        $response =
            $this->httpClient->request(Request::METHOD_POST, $endpointUrl, [
                'verify_peer' => false,
                'verify_host' => false,
                'body' => [
                    'issue-url'         => $bug->getRemoteUrl(),
                    'issue-remote-id'   => $bug->getRemoteId(),
                    'post-id'           => $bug->getPost()->getId()
                ],
            ]);

        $statusCode = $response->getStatusCode();
        return $response->getContent();
    }

    public function getPost() : Post { return $this->post; }
}
