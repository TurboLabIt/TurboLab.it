<?php
// clear && curl --insecure -X DELETE -F "topic-id=12690" https://dev0.turbolab.it/comments-topic-delete/

const TLI_PROJECT_DIR = '/var/www/turbolab.it/';
$db = $user = $auth = null;

const THIS_SPECIAL_PAGE_PATH = "/comments-topic-delete/";
require TLI_PROJECT_DIR . 'public/special-pages/includes/00_begin.php';


if( !in_array($_SERVER['REMOTE_ADDR'] ?? null, ['127.0.0.1']) ) {
    tliHtmlResponse('This page is for internal use only', 403);
}

if( $_SERVER['REQUEST_METHOD'] !== 'DELETE' ) {
    tliHtmlResponse('This page requires the DELETE method', 405);
}

$topicId = $_GET['topic-id'] ?? null;
if( empty($topicId) ) {
    tliHtmlResponse('Invalid parameter', 400);
}

$topicId = (int)$topicId;

require TLI_PROJECT_DIR . 'public/special-pages/includes/10_phpbb_start.php';
require($phpbb_root_path . 'includes/functions_admin.' . $phpEx);

// ensure the comments topic exists
tliGetCommentsTopicById($topicId);

delete_topics('topic_id', [$topicId]);

tliHtmlResponse("Topic $topicId deleted", 200);
