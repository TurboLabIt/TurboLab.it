<?php
/*
clear && curl --insecure -X POST -F "issue-url=https://github.com/TurboLabIt/TurboLab.it/issues/00-test" -F "issue-remote-id=00-test" -F "post-id=XXXXXX" -F "user-id=5103" https://XXXX.turbolab.it/issue-add-to-post/
 */

const TLI_PROJECT_DIR = '/var/www/turbolab.it/';
$db = null;


const THIS_SPECIAL_PAGE_PATH = "/issue-add-to-post/";
require TLI_PROJECT_DIR . 'public/special-pages/includes/00_begin.php';


if( !in_array($_SERVER['REMOTE_ADDR'] ?? null, ['127.0.0.1'])  ) {
    tliHtmlResponse('This page is for internal use only', 403);
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


$userId = (int)$userId;
if($userId < 1 ) {
    tliHtmlResponse('Invalid user ID', 400);
}

require TLI_PROJECT_DIR . 'public/special-pages/includes/10_phpbb_start.php';


$post       = tliGetPostById($postId);
$topic      = tliGetTopicById($post['topic_id']);
$forum      = tliGetForumById($post['forum_id']);
$postAuthor = tliGetUserById($post['poster_id']);


// get current message as raw BBCode
$message = $post['post_text'];
decode_message($message, $post['bbcode_uid']);
$message .= "\n\n[b]🪲 [url=$issueUrl]Issue #$issueRemoteId su GitHub[/url][/b]";

// re-prepare for storage
$message_parser = new parse_message($message);
$message_parser->parse(true, true, true);

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
    'message'           => $message_parser->message,
    'message_md5'       => md5($message_parser->message),
    'bbcode_uid'        => $message_parser->bbcode_uid,
    'bbcode_bitfield'   => $message_parser->bbcode_bitfield,
    'bbcode_options'    => 7, // bbcode + smilies + urls all enabled
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
