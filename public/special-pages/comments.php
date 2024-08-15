<?php
/**
 * @link https://github.com/TurboLabIt/TurboLab.it/tree/main/docs/comments.md
 *
 * https://turbolab.it/ajax/login/
 *
 * 400: generic client error
 * 500: server error
 * 200: OK
 */
require '../../src/Service/PhpBB/ForumUrlGenerator.php';
const THIS_SPECIAL_PAGE_PATH = "/" . App\Service\PhpBB\ForumUrlGenerator::AJAX_LOADING_PATH;
require './includes/00_begin.php';

$topicId = $_GET['id'] ?? '';
if( preg_match('/^[1-9]+[0-9]*$/', $topicId) !== 1 ) {
    tliHtmlResponse('Formato input errato', 400);
}

require '../../src/Entity/PhpBB/Forum.php';
$commentsForumId = \App\Entity\PhpBB\Forum::COMMENTS_FORUM_ID;
if( preg_match('/^[1-9]+[0-9]*$/', $commentsForumId) !== 1 ) {
    tliHtmlResponse("Errore accesso forum commenti. $txtPleaseReport", 500);
}

require './includes/10_phpbb_start.php';

$sqlSelectTopic = '
    SELECT * FROM ' . TOPICS_TABLE . ' AS topics
    WHERE
      topic_id          = ' . $topicId . ' AND
      topic_visibility  = ' . ITEM_APPROVED . ' AND
      topic_delete_time = 0 AND
      forum_id          NOT IN (' . implode(',', \App\Entity\PhpBB\Forum::OFFLIMITS_FORUM_IDS) . ')
';

$result     = $db->sql_query($sqlSelectTopic);
$arrTopic   = $db->sql_fetchrow($result);

if( empty($arrTopic) ) {
    tliHtmlResponse("Topic ##" . $topicId . "## non trovato. $txtPleaseReport", 500);
}

if( $arrTopic["forum_id"] != $commentsForumId ) {
    tliHtmlResponse(
        "Impossibile accedere al topic dei commenti ##$topicId##. Questo topic " .
        "non fa parte del <a href=\"/forum/viewforum.php?f=26\">forum dedicato ai commenti</a>. $txtPleaseReport",
        500
    );
}


$sqlSelectTopic ='
    SELECT * FROM ' . POSTS_TABLE . ' AS posts
    WHERE
      topic_id          = ' . $topicId . ' AND
      post_id          != ' . $arrTopic["topic_first_post_id"] . ' AND
      forum_id          = ' . $commentsForumId . ' AND
      post_visibility   = ' . ITEM_APPROVED . ' AND
      post_delete_time  = 0 AND
      sfs_reported      = 0
    ORDER BY post_time ASC
';

$result = $db->sql_query($sqlSelectTopic);

while(true && $arrPost = $db->sql_fetchrow($result) ) {
    var_dump($arrPost);
}

while( $arrPost = $db->sql_fetchrow($result) ) { ?>

    <div class="post-comments-item">
        <div class="thumb">
            <img src="assets/images/comments-1.png" alt="comments">
        </div>
        <div class="post">
            <a href="#">Reply</a>
            <h5 class="title">Subash Chandra</h5>
            <p>We’ve invested every aspect of how we serve our users over the past Pellentesque rutrum ante in nulla suscipit, vel posuere leo tristique.</p>
        </div>
    </div>


<?php
    //var_dump($arrPost);
}
