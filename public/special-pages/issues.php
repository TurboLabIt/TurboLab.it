<?php
/*
clear && curl --insecure -X POST -F "issue-url=https://github.com/TurboLabIt/TurboLab.it/issues/00-test" -F "issue-remote-id=00-test" -F "post-id=XXXXXX" -F "user-id=5103" https://XXXX.turbolab.it/issue-add-to-post/
 */

define('TLI_PROJECT_DIR', '/var/www/turbolab.it/');
$phpbb_root_path = $phpEx = $db = null;


const THIS_SPECIAL_PAGE_PATH = "/issue-add-to-post/";
require TLI_PROJECT_DIR . 'public/special-pages/includes/00_begin.php';


if( !in_array($_SERVER['REMOTE_ADDR'] ?? null, ['127.0.0.1'])  ) {
    tliHtmlResponse('Questa pagina è disponibile solo da localhost', 403);
}

if( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    tliHtmlResponse('This page requires the POST method', 405);
}


$issueUrl       = $_POST['issue-url'] ?? null;
$issueRemoteId  = $_POST['issue-remote-id'] ?? null;
$postId         = $_POST['post-id'] ?? null;
$userId         = $_POST['user-id'] ?? null;

foreach([&$issueUrl, &$issueRemoteId, &$postId, &$userId] as &$var) {

    $var = trim($var ?? '');
    if( empty($var) ) {
        tliHtmlResponse('Invalid parameter', 400);
    }
}

$postId = (int)$postId;
if( $postId < 1 ) {
    tliHtmlResponse('Invalid post ID', 400);
}

$userId = (int)$userId;
if($userId < 1 ) {
    tliHtmlResponse('Invalid user ID', 400);
}


require TLI_PROJECT_DIR . 'public/special-pages/includes/10_phpbb_start.php';
require($phpbb_root_path . 'includes/functions_posting.' . $phpEx);


$sqlSelect = 'SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = ' . $postId;
$result = $db->sql_query($sqlSelect);
$post = $db->sql_fetchrow($result);
$db->sql_freeresult($result);
if(!$post) { tliHtmlResponse('Post not found', 404); }


$sqlSelect = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int)$post['topic_id'] . ' AND forum_id = ' . (int)$post['forum_id'];
$result = $db->sql_query($sqlSelect);
$topic = $db->sql_fetchrow($result);
$db->sql_freeresult($result);
if(!$topic) { tliHtmlResponse('Topic not found', 404); }


$sql = 'SELECT forum_name FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int)$post['forum_id'];
$result = $db->sql_query($sql);
$forum = $db->sql_fetchrow($result);
$db->sql_freeresult($result);
if(!$forum) { tliHtmlResponse('Forum not found', 404); }


$sql = 'SELECT username FROM ' . USERS_TABLE  . ' WHERE user_id  = ' . (int)$post['poster_id'];
$result = $db->sql_query($sql);
$postAuthor = $db->sql_fetchrow($result);
$db->sql_freeresult($result);
if(!$postAuthor) { tliHtmlResponse('User not found', 404); }


// get current message as raw BBCode
$message = $post['post_text'];
decode_message($message, $post['bbcode_uid']);
$message .= "\n\n[b]🪲 [url=$issueUrl]Issue #$issueRemoteId su GitHub[/url][/b]";

// re-prepare for storage
$uid = $bitfield = $options = '';
generate_text_for_storage($message, $uid, $bitfield, $options, true, true, true);

$data = [
    'post_id'   => (int)$post['post_id'],
    'topic_id'  => (int)$post['topic_id'],
    'forum_id'  => (int)$post['forum_id'],

    'topic_title'               => $topic['topic_title'],
    'topic_posts_approved'      => (int)$topic['topic_posts_approved'],
    'topic_posts_unapproved'    => (int)$topic['topic_posts_unapproved'],
    'topic_posts_softdeleted'   => (int)$topic['topic_posts_softdeleted'],
    'topic_first_post_id'       => (int)$topic['topic_first_post_id'],
    'topic_last_post_id'        => (int)$topic['topic_last_post_id'],

    'forum_name'    => $forum['forum_name'],
    'post_subject'  => $post['post_subject'],

    'poster_id'         => (int)$post['poster_id'],
    'post_username'     => '',

    'icon_id'           => (int)($post['icon_id'] ?? 0),
    'enable_sig'        => (bool)($post['enable_sig'] ?? false),
    'post_edit_locked'  => (int)($post['post_edit_locked'] ?? 0),

    // message + parsing info (fixes: message_md5)
    'message'           => $message,
    'message_md5'       => md5($message),
    'bbcode_uid'        => $uid,
    'bbcode_bitfield'   => $bitfield,
    'bbcode_options'    => $options,
    'enable_bbcode'     => true,
    'enable_smilies'    => true,
    'enable_urls'       => true,
    'enable_indexing'   => true,

    'post_edit_reason'  => "Link to GitHub issue #$issueRemoteId",
    'post_edit_user'    => $userId
];

$data['post_visibility'] = (int)$post['post_visibility'];
if( defined('ITEM_APPROVED') && (int)$post['post_visibility'] === ITEM_APPROVED ) {
    $data['force_approved_state'] = true;
}

$arrPoll = [];
submit_post('edit', $post['post_subject'],  $postAuthor['username'] ?? '', POST_NORMAL, $arrPoll, $data);

$postUrl = TLI_SITE_URL . "/forum/viewtopic.php?p=$postId#p$postId";
tliHtmlResponse("🚀 Post $postUrl updated with link to $issueUrl", 200);
