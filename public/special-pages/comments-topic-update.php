<?php
// clear && curl --insecure -X POST -F "article-id=4423" https://dev0.turbolab.it/commenti/topic-upsert/

const TLI_PROJECT_DIR = '/var/www/turbolab.it/';
$db = $user = $auth = null;

const THIS_SPECIAL_PAGE_PATH = "/comments-topic-update/";
require TLI_PROJECT_DIR . 'public/special-pages/includes/00_begin.php';


if( !in_array($_SERVER['REMOTE_ADDR'] ?? null, ['127.0.0.1']) ) {
    tliHtmlResponse('This page is for internal use only', 403);
}

if( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    tliHtmlResponse('This page requires the POST method', 405);
}

$postTitle  = $_POST['post-title'] ?? null;
$postBody   = $_POST['post-body'] ?? null;
$authorId   = $_POST['author-id'] ?? null;
$topicId    = $_POST['topic-id'] ?? null;

foreach([&$postTitle, &$postBody, &$authorId, &$topicId] as &$var) {

    $var = trim($var ?? '');
    if( empty($var) ) {
        tliHtmlResponse('Invalid parameter', 400);
    }
}

$titleNormalized = App\Service\HtmlProcessorBase::decode($postTitle);
unset($postTitle);
// phpBB come salva l'HTML a database? https://turbolab.it/forum/viewtopic.php?t=13553
$titleForPhpBB= htmlspecialchars($titleNormalized);


require TLI_PROJECT_DIR . 'public/special-pages/includes/10_phpbb_start.php';

// prepare for storage
$message_parser = new parse_message($postBody);
$message_parser->parse(true, true, true);

$topic      = tliGetTopicById($topicId);
$post       = tliGetPostById($topic['topic_first_post_id']);
$forum      = tliGetForumById($topic['forum_id']);
$postAuthor = tliGetUserById($authorId);

$data = [
    'post_id'   => $post['post_id'],
    'topic_id'  => $topic['topic_id'],
    'forum_id'  => (int)$post['forum_id'],

    'topic_title'               => $titleForPhpBB,
    'topic_posts_approved'      => (int)$topic['topic_posts_approved'],
    'topic_posts_unapproved'    => (int)$topic['topic_posts_unapproved'],
    'topic_posts_softdeleted'   => (int)$topic['topic_posts_softdeleted'],
    'topic_first_post_id'       => (int)$topic['topic_first_post_id'],
    'topic_last_post_id'        => (int)$topic['topic_last_post_id'],

    'forum_name'    => $forum['forum_name'],
    'post_subject'  => $titleForPhpBB,

    'poster_id'         => (int)$authorId,
    'post_username'     => '',

    'icon_id'           => 7,
    'enable_sig'        => false,
    'post_edit_locked'  => 1,

    // message + parsing info (fixes: message_md5)
    'message'           => $message_parser->message,
    'message_md5'       => md5($message_parser->message),
    'bbcode_uid'        => $message_parser->bbcode_uid,
    'bbcode_bitfield'   => $message_parser->bbcode_bitfield,
    'bbcode_options'    => 7, // bbcode + smilies + urls all enabled
    'enable_bbcode'     => true,
    'enable_smilies'    => true,
    'enable_urls'       => true,
    'enable_indexing'   => true,

    'post_edit_reason'  => '',
    'post_edit_user'    => null,

    'force_approved_state'  => true,
    'force_visibility'      => true,

    'notify'        => true,
    'notify_set'    => false,
];

$userBackup = $user;
$authBackup = $auth;
$user->data = array_merge($user->data, $postAuthor);
$auth->acl($user->data);
$user->ip = '0.0.0.0';

$arrPoll = [];
$result = submit_post('edit', $titleForPhpBB,  $postAuthor['username'] ?? '', POST_NORMAL, $arrPoll, $data);

$user = $userBackup;
$auth = $authBackup;


// submit_post can't update all the fields => manual fixup via SQL is required
if( $topic['topic_last_poster_id'] == 1 ) {

    $lastPosterId       = $postAuthor["user_id"];
    $lastPosterName     = $postAuthor["username"];
    $lastPosterColour   = $postAuthor["user_colour"];

} else {

    $lastPosterId       = $topic['topic_last_poster_id'];
    $lastPosterName     = $topic['topic_last_poster_name'];
    $lastPosterColour   = $topic['topic_last_poster_colour'];
}

$arrTopicUpdateParams = [
    'topic_poster'              => $postAuthor['user_id'],
    'topic_first_poster_colour' => $postAuthor['user_colour'],

    'topic_last_poster_id'      => $lastPosterId,
    'topic_last_poster_name'    => $lastPosterName,
    'topic_last_poster_colour'  => $lastPosterColour
];

$sqlTopicUpdate =
    'UPDATE ' . TOPICS_TABLE . ' SET ' .
        $db->sql_build_array('UPDATE', $arrTopicUpdateParams) .
    ' WHERE topic_id = ' . $topic['topic_id'];

$db->sql_query($sqlTopicUpdate);


$postUrl = TLI_SITE_URL . "/forum/" . str_ireplace(['../', './'], '', $result);
tliHtmlResponse($postUrl, 200);
