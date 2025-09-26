<?php
// 📚 https://www.phpbb.com/support/docs/en/3.0/kb/article/phpbb3-cross-site-sessions-integration/
define('IN_PHPBB', true);
chdir(TLI_PROJECT_DIR . "public/forum/");
$phpbb_root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
$user = $auth = null;

require($phpbb_root_path . 'common.' . $phpEx);
require($phpbb_root_path . 'includes/functions_user.' . $phpEx);
require($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
require($phpbb_root_path . 'includes/message_parser.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
$user->setup();


function tliGetPostById(int $postId) : array
{
    return
        tliGetPhpBBSomethingById(
            $postId, 'post',
            "SELECT * FROM " . POSTS_TABLE . " WHERE post_id = $postId",
            true
        );
}


function tliGetTopicById(int $topicId) : array
{
    return
        tliGetPhpBBSomethingById(
            $topicId, 'topic',
            "SELECT * FROM " . TOPICS_TABLE . " WHERE topic_id = $topicId",
            true
        );
}


function tliGetForumById(int $forumId) : array
{
    return
        tliGetPhpBBSomethingById(
            $forumId, 'forum',
            "SELECT * FROM " . FORUMS_TABLE . " WHERE forum_id = $forumId",
            true
        );
}


function tliGetUserById(int $userId) : array
{
    return
        tliGetPhpBBSomethingById(
            $userId, 'user',
            "SELECT * FROM " . USERS_TABLE  . " WHERE user_id = $userId",
            false
        );
}


function tliGetPhpBBSomethingById(int $id, string $entityName, string $sqlQuery, bool $excludeOfflimitForums) : array
{
    global $db;

    if( empty($id) || $id < 1 ) {
        tliHtmlResponse("Invalid $entityName ID", 400);
    }

    if($excludeOfflimitForums) {
        $sqlQuery .= " AND forum_id NOT IN(" . implode(',', \App\Entity\PhpBB\Forum::ID_OFFLIMIT) . ")";
    }

    $result = $db->sql_query($sqlQuery);
    $entity = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if( empty($entity) ) {
        tliHtmlResponse(mb_ucfirst($entityName) . ' not found', 404);
    }

    return $entity;
}
