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

require '../../src/Entity/BaseEntity.php';
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
        "non fa parte del <a href=\"/forum/viewforum.php?f=$commentsForumId\">forum dedicato ai commenti</a>. $txtPleaseReport",
        500
    );
}


$sqlSelectRanks = 'SELECT * FROM ' . RANKS_TABLE . ' ORDER BY rank_min DESC';
$result = $db->sql_query($sqlSelectRanks);

$arrRankTable = [];
while( $arrRank = $db->sql_fetchrow($result) ) {

    if( $arrRank["rank_special"] == 1 ) {

        $id = $arrRank["rank_id"];
        $arrRankTable["specials"][$id] = $arrRank;

    } else {

        $minPosts = (string)$arrRank["rank_min"];
        $arrRankTable["regulars"][$minPosts] = $arrRank;
    }
}


$sqlSelectPosts = '
    SELECT * FROM ' . POSTS_TABLE . ' AS posts
    LEFT JOIN ' . USERS_TABLE . ' AS users
    ON posts.poster_id = users.user_id
    WHERE
      topic_id          = ' . $topicId . ' AND
      post_id          != ' . $arrTopic["topic_first_post_id"] . ' AND
      forum_id          = ' . $commentsForumId . ' AND
      post_visibility   = ' . ITEM_APPROVED . ' AND
      post_delete_time  = 0 AND
      sfs_reported      = 0
    ORDER BY post_time ASC
';

$result = $db->sql_query($sqlSelectPosts);

while( $arrPost = $db->sql_fetchrow($result) ) {

    // 📚 https://area51.phpbb.com/docs/dev/master/extensions/tutorial_parsing_text.html#displaying-text-from-db
    $arrPost['bbcode_options'] =
        (($arrPost['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) +
        (($arrPost['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) +
        (($arrPost['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);

    $arrPost["tli_username_style"] =
        empty($arrPost['user_colour']) ? '' : 'style="color: #' . $arrPost["user_colour"] . '"';

    $rankId = $arrPost['user_rank'];
    if( !empty($rankId) && array_key_exists($rankId, $arrRankTable["specials"]) ) {

        $arrPost['tli_rank'] = $arrRankTable["specials"][$rankId];

    } else {

        $userPostNum = $arrPost["user_posts"];
        foreach($arrRankTable["regulars"] ?? [] as $minPost => $rank) {

            if( $userPostNum >= $minPost ) {

                $arrPost['tli_rank'] = $rank;
                break;
            }
        }
    }

    $arrPost["tli_rank_image"] =
        empty( $arrPost['tli_rank']['rank_image'] )
            ? '' : '<img src="/forum/images/ranks/' . $arrPost['tli_rank']['rank_image'] . '" class="">';

    $arrPost["tli_text"] =
        generate_text_for_display(
            $arrPost['post_text'], $arrPost['bbcode_uid'], $arrPost['bbcode_bitfield'], $arrPost['bbcode_options']
        );

    $arrPost["tli_text"] = str_replace(
        '<img class="smilies" src="./../../../../public/forum/images/smilies/',
        '<img class="smilies" src="/forum/images/smilies/', $arrPost["tli_text"]
    );
?>

    <div class="post-comments-item">
        <div class="post">
            <h5 class="title" <?php echo $arrPost["tli_username_style"] ?>>
                <span><?php echo $arrPost["username"] ?></span> &nbsp; <?php echo $arrPost["tli_rank_image"] ?>
            </h5>
            <div class="tli-comment-main-content"><?php echo $arrPost["tli_text"] ?></div>
            <div class="tli-comment-reply">
                <a href="/forum/posting.php?mode=reply&t=<?php echo $topicId ?>">Rispondi</a> |
                <a href="/forum/posting.php?mode=quote&p=<?php echo $arrPost["post_id"] ?>">Rispondi citando</a>
            </div>
        </div>
    </div>

    <hr>

<?php
}
