<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use App\Entity\PhpBB\Topic;
use App\Service\Newsletter;
use DateTime;
use Doctrine\DBAL\Connection;
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


    public function insertNewRow(string $title, string $plainTextBody, int $forumId) : Topic
    {
        if( $forumId <= 0 ) {
            throw new InvalidArgumentException('Invalid forum ID');
        }

        $titleForPhpBB = \App\Service\PhpBB\Topic::encodeTextAsTitle($title);

        $connection = $this->getEntityManager()->getConnection();

        try {

            $newTopicId =
                $connection->transactional(function(Connection $connection) use ($titleForPhpBB, $plainTextBody, $forumId) {

                    $sqlInsertTopic = "
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
                            topic_visibility        = :visibility
                    ";

                    $connection->executeQuery($sqlInsertTopic, [
                        'forumId'           => $forumId,
                        'title'             => $titleForPhpBB,

                        'posterUserId'      => 1,
                        'posterUserName'    => 'TurboLab.it',

                        'visibility'        => Topic::ITEM_APPROVED
                    ]);

                    $newTopicId = $connection->lastInsertId();


                    $sqlInsertPost = "
                        INSERT INTO " . $this->getPhpBBTableName('posts', false) . " SET
                            topic_id            = :topicId,
                            forum_id            = :forumId,
                            post_subject        = :title,

                            poster_id           = :posterUserId,
                            post_username       = :posterUserName,
                            poster_ip           = '127.0.0.1',
                            post_time           = UNIX_TIMESTAMP(),

                            post_text           = :body,
                            bbcode_bitfield     = '',
                            bbcode_uid          = '',
                            post_visibility     = :visibility,
                            enable_bbcode       = 1,
                            enable_smilies      = 1,
                            enable_magic_url    = 1
                    ";

                    $connection->executeQuery($sqlInsertPost, [
                        'topicId'           => $newTopicId,
                        'forumId'           => $forumId,
                        'title'             => $titleForPhpBB,

                        'posterUserId'      => 1,
                        'posterUserName'    => 'TurboLab.it',

                        'body'              => trim(strip_tags($plainTextBody)),
                        'visibility'        => Topic::ITEM_APPROVED
                    ]);

                    $postId = $connection->lastInsertId();


                    $sqlUpdateTopic = "
                        UPDATE " . $this->getPhpBBTableName('topics', false) . " SET
                            topic_first_post_id     = :postId,
                            topic_last_post_id      = :postId,
                            topic_posts_approved    = 1
                        WHERE topic_id = :topicId
                    ";

                    $connection->executeQuery($sqlUpdateTopic, [
                        'postId'    => $postId,
                        'topicId'   => $newTopicId
                    ]);


                    $sqlUpdateForum = "
                        UPDATE " . $this->getPhpBBTableName('forums', false) . " SET
                            forum_posts_approved    = forum_posts_approved + 1,
                            forum_topics_approved   = forum_topics_approved + 1,
                            forum_last_post_time    = UNIX_TIMESTAMP()
                        WHERE forum_id = :forumId
                    ";

                $connection->executeQuery($sqlUpdateForum, [
                    'postId'            => $postId,
                    'posterUserId'      => 1,
                    'title'             => $titleForPhpBB,
                    'posterUserName'    => 'TurboLab.it',
                    'forumId'           => $forumId,
                ]);

                return $newTopicId;
            });

        } catch(\Exception $ex) {
            throw $ex;
        }

        return $this->getOneById($newTopicId);
    }


    public function countNewOfTheYear() : int
    {
        $yearStart  = new DateTime('first day of January this year 00:00:00');
        $yearEnd    = new DateTime('last day of December this year 23:59:59');

        return
            // warning! we're using parent::getQueryBuilder() instead of $this->getQueryBuilder() to skip some conditions
            // this is fine **as long as it's a count**
            parent::getQueryBuilder()
                ->select('COUNT(t)')
                ->andWhere('t.time >= :yearStart')
                    ->setParameter('yearStart', $yearStart->getTimestamp())
                ->andWhere('t.time <= :yearEnd')
                    ->setParameter('yearEnd', $yearEnd->getTimestamp())
                ->getQuery()->getSingleScalarResult();
    }
}
