<?php
namespace App\Controller;

use App\Service\Issue;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class ForumController extends BaseController
{
    const string SLUG = 'forum-integration/';


    #[Route(self::SLUG . 'ajax/new-issue', name: 'app_forum_new_issue', methods: ['POST'])]
    public function newIssue(Issue $issue): Response
    {
        $this->ajaxOnly();

        $postId = (int)$this->request->get('postId');

        try {

            $issue
                ->rateLimiting($this->getCurrentUserAsAuthor(), $this->request->getClientIp() )
                ->createFromForumPostId($postId, $this->getCurrentUserAsAuthor(), $this->request->getClientIp() );

        } catch(Exception $ex) {

            return $this->textErrorResponse($ex);
        }

        return new Response( $issue->getPost()->getUrl() );
    }
}
