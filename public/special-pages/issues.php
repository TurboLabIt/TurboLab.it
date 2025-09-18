<?php
/*
clear && curl --insecure -X POST -F "issue-url=https://github.com/TurboLabIt/TurboLab.it/issues/3" -F "issue-remote-id=3" -F "post-id=103083" https://turbolab.it/issue-add-to-post/
 */

const THIS_SPECIAL_PAGE_PATH = "/issue-add-to-post/";
require './includes/00_begin.php';

if( !in_array($_SERVER['REMOTE_ADDR'] ?? null, ['127.0.0.1'])  ) {
    tliResponse('Questa pagina è disponibile solo da localhost', 403);
}

if( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    tliResponse('This page requires the POST method', 405);
}

$issueUrl       = $_POST['issue-url'] ?? null;
$issueRemoteId  = $_POST['issue-remote-id'] ?? null;
$postId         = $_POST['post-id'] ?? null;
$userId         = $_POST['user-id'] ?? null;

foreach([&$issueUrl, &$issueRemoteId, &$postId, &$userId] as &$var) {

    $var = trim($var);
    if( empty($var) ) {
        tliResponse('Invalid parameter', 400);
    }
}

$postId = (int)$postId;
if( $postId < 1 ) {
    tliResponse('Invalid post ID', 400);
}

$userId = (int)$userId;
if( $userId < 1 ) {
    tliResponse('Invalid user ID', 400);
}

require './includes/10_phpbb_start.php';
require($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
//require($phpbb_root_path . 'includes/functions_content.' . $phpEx);


$sqlSelect = 'SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = ' . $postId;

$result = $db->sql_query($sqlSelect);
$row = $db->sql_fetchrow($result);
$db->sql_freeresult($result);
if(!$row) { tliResponse('Post not found', 404); }


$sqlSelect = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $row['topic_id'] . ' AND forum_id = ' . (int)$row['forum_id'];
$result = $db->sql_query($sqlSelect);
$topic = $db->sql_fetchrow($result);
$db->sql_freeresult($result);
if(!$topic) { tliResponse('Topic not found', 404); }


$sql = 'SELECT forum_name FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int)$row['forum_id'];
$result = $db->sql_query($sql);
$forum = $db->sql_fetchrow($result);
$db->sql_freeresult($result);
if(!$forum) { tliResponse('Forum not found', 404); }


// get current message as raw BBCode
$message = $row['post_text'];
decode_message($message, $row['bbcode_uid']);
$message .= "\n\n[b]🪲 [url=$issueUrl]Issue #$issueRemoteId su GitHub[/url][/b]";

// re-prepare for storage
$uid = $bitfield = $options = '';
generate_text_for_storage($message, $uid, $bitfield, $options, true, true, true);

$data = [
    'post_id'   => (int)$row['post_id'],
    'topic_id'  => (int)$row['topic_id'],
    'forum_id'  => (int)$row['forum_id'],

    'topic_posts_approved'      => (int)$topic['topic_posts_approved'],
    'topic_posts_unapproved'    => (int)$topic['topic_posts_unapproved'],
    'topic_posts_softdeleted'   => (int)$topic['topic_posts_softdeleted'],
    'topic_first_post_id'       => (int)$topic['topic_first_post_id'],
    'topic_last_post_id'        => (int) $topic['topic_last_post_id'],

    'forum_name'    => $forum['forum_name'],
    'post_subject'  => $row['post_subject'],

    'poster_id'         => (int)$row['poster_id'],
    'icon_id'           => (int)($row['icon_id'] ?? 0),
    'enable_sig'        => (bool)($row['enable_sig'] ?? false),
    'post_edit_locked'  => (int)($row['post_edit_locked'] ?? 0),

    // message + parsing info (fixes: message_md5)
    'topic_title'       => $row['post_subject'],
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

$data['post_visibility'] = (int) $row['post_visibility'];
if (defined('ITEM_APPROVED') && (int)$row['post_visibility'] === ITEM_APPROVED) {
    $data['force_approved_state'] = true;
}

$arrPoll = [];
submit_post('edit', $row['post_subject'],  $row['post_username'], POST_NORMAL, $arrPoll, $data);

$postUrl = "$siteUrl/forum/viewtopic.php?p=$postId#p$postId";
tliHtmlResponse("🚀 Post $postUrl updated with link to $issueUrl", 200);
