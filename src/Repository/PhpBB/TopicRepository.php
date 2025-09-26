<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use App\Entity\PhpBB\Topic;
use App\Service\Newsletter;
use Doctrine\ORM\QueryBuilder;
use http\Exception\InvalidArgumentException;


class TopicRepository extends BasePhpBBRepository
{
    const string ENTITY_CLASS       = Topic::class;
    const string DEFAULT_INDEXED_BY = 't.id';


    protected function getQueryBuilder() : QueryBuilder
    {
        return
            parent::getQueryBuilder()
                ->andWhere('t.forumId NOT IN (' . implode(',', Forum::ID_OFFLIMIT) . ')')
                ->andWhere('t.visibility = 1')
                ->andWhere('t.deleteTime = 0')
                ->orderBy('t.lastPostTime', 'DESC');
    }


    protected function getSqlSelectQuery() : string
    {
        return "
            SELECT topic_id
            FROM " . $this->getPhpBBTableName() . "
            WHERE
              (
                forum_id != " . Forum::ID_COMMENTS . " OR
                (forum_id = " . Forum::ID_COMMENTS . " AND topic_posts_approved > 1)
              ) AND
            forum_id NOT IN (" . implode(',', Forum::ID_OFFLIMIT) . ")
        ";
    }


    public function findLatest(?int $num = null) : array
    {
        $num = $num ?? 10;

        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                $this->getSqlSelectQuery() . "
                ORDER BY topic_last_post_time DESC
                LIMIT $num
            ");

        if( empty($qb) ) {
            return [];
        }

        return
            $qb
                ->orderBy('t.lastPostTime', 'DESC')
                ->getQuery()->getResult();
    }


    public function findLatestForNewsletter() : array
    {
        $sqlSelect = $this->getSqlSelectQuery() . "
            AND FROM_UNIXTIME(topic_last_post_time) BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) AND NOW()
        ";

        foreach(Newsletter::FORBIDDEN_WORDS as $word) {
            $sqlSelect .= " AND topic_title NOT LIKE '%$word%'";
        }

        $sqlSelect .= "
            ORDER BY topic_views DESC
            LIMIT 25
        ";

        $qb = $this->getQueryBuilderCompleteFromSqlQuery($sqlSelect);

        if( empty($qb) ) {
            return [];
        }

        return
            $qb
                ->orderBy('t.views', 'DESC')
                ->getQuery()->getResult();
    }


    public function getRandomComplete(?int $num = null) : array
    {
        $num = $num ?? 10;

        $qb =
            $this->getQueryBuilderCompleteFromSqlQuery(
                $this->getSqlSelectQuery() . "
                ORDER BY
                    RAND() LIMIT $num
            ");

        if( empty($qb) ) {
            return [];
        }

        return $qb->getQuery()->getResult();
    }


    public function insertNewRow(string $title, int $forumId) : Topic
    {
        if( $forumId <= 0 ) {
            throw new InvalidArgumentException('Invalid forum ID');
        }

        // 👇🏻 the most aggressive version I can think of!
        $titleNormalized = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $titleNormalized = trim($titleNormalized);
        // phpBB come salva l'HTML a database? https://turbolab.it/forum/viewtopic.php?t=13553
        $titleForPhpBB = htmlentities($titleNormalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $sqlInsert = "
            START TRANSACTION;

            INSERT INTO " . $this->getPhpBBTableName('topics', false) . " SET
                forum_id                = :forumId,
                topic_title             = :title,
                topic_last_post_subject = :title,

                topic_poster            = :posterUserId,
                topic_first_poster_name = :posterUserName,
                topic_last_poster_id    = :posterUserId,
                topic_last_poster_name  = :posterUserName,

                topic_last_post_time    = UNIX_TIMESTAMP(),
                topic_time              = UNIX_TIMESTAMP(),
                topic_visibility        = :visibility;

            SET @newTopicId = LAST_INSERT_ID();

            INSERT INTO " . $this->getPhpBBTableName('posts', false) . " SET
                topic_id            = @newTopicId,
                forum_id            = :forumId,
                post_subject        = :title,

                poster_id           = :posterUserId,
                post_username       = :posterUserName,
                poster_ip           = '127.0.0.1',
                post_time           = UNIX_TIMESTAMP(),

                post_text           = :title,
                bbcode_bitfield     = '',
                bbcode_uid          = '',
                post_visibility     = :visibility,
                enable_bbcode       = 1,
                enable_smilies      = 1,
                enable_magic_url    = 1;

            SET @newPostId = LAST_INSERT_ID();

            UPDATE " . $this->getPhpBBTableName('topics', false) . " SET
                topic_first_post_id = @newPostId,
                topic_last_post_id  = @newPostId

            WHERE topic_id = @newTopicId;

            UPDATE " . $this->getPhpBBTableName('forums', false) . " SET
                forum_posts_approved    = forum_posts_approved + 1,
                forum_topics_approved   = forum_topics_approved + 1,
                forum_last_post_id      = @newPostId,
                forum_last_poster_id    = :posterUserId,
                forum_last_post_subject = :title,
                forum_last_post_time    = UNIX_TIMESTAMP(),
                forum_last_poster_name  = :posterUserName
            WHERE forum_id = :forumId;

            COMMIT;
        ";

        $arrParams = [
            'forumId'           => $forumId,
            'title'             => $titleForPhpBB,
            'posterUserId'      => 1,
            'posterUserName'    => 'TurboLab.it',
            'visibility'        => Topic::ITEM_APPROVED
        ];

        $this->sqlQueryExecute($sqlInsert, $arrParams);

        $topicId = $this->sqlQueryExecute('SELECT @newTopicId AS topic_id')->fetchOne();

        return $this->getOneById($topicId);
    }
}
