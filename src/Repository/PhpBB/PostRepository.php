<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use App\Entity\PhpBB\Post;
use DateTime;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;


class PostRepository extends BasePhpBBRepository
{
    const string ENTITY_CLASS       = Post::class;
    const string DEFAULT_INDEXED_BY = 't.id';


    protected function getQueryBuilder() : QueryBuilder
    {
        return
            parent::getQueryBuilder()
                ->andWhere('t.forumId NOT IN (' . implode(',', Forum::ID_OFFLIMIT) . ')')
                ->andWhere('t.visibility = 1')
                ->andWhere('t.deleteTime = 0')
                ->orderBy('t.postTime', 'DESC');
    }


    public function countNewOfTheYear() : int
    {
        $yearStart  = new DateTime('first day of January this year 00:00:00');
        $yearEnd    = new DateTime('last day of December this year 23:59:59');

        return (int)
            // warning! we're using parent::getQueryBuilder() instead of $this->getQueryBuilder() to skip some conditions
            // this is fine **as long as it's a count**
            parent::getQueryBuilder()
                ->select('COUNT(t)')
                ->andWhere('t.postTime >= :yearStart')
                    ->setParameter('yearStart', $yearStart->getTimestamp())
                ->andWhere('t.postTime <= :yearEnd')
                    ->setParameter('yearEnd', $yearEnd->getTimestamp())
                ->getQuery()->getSingleScalarResult();

    }


    /**
     * Returns ['YYYY-MM-DD' => int] — count of visible, non-deleted posts in non-off-limit forums per day.
     */
    public function getPostsByDay(\DateTimeInterface $start, \DateTimeInterface $end) : array
    {
        $startTs    = (int)$start->format('U');
        $endTs      = (int)$end->format('U') + 86399;

        $offlimits  = implode(',', array_map('intval', Forum::ID_OFFLIMIT));

        $sql = "
            SELECT
                DATE(FROM_UNIXTIME(posts.post_time)) AS day,
                COUNT(*) AS cnt
            FROM " . $this->getPhpBBTableName() . "
            WHERE
                posts.post_time BETWEEN :startTs AND :endTs AND
                posts.post_visibility = 1 AND
                posts.post_delete_time = 0 AND
                posts.forum_id NOT IN($offlimits)
            GROUP BY day
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('startTs', $startTs, ParameterType::INTEGER);
        $stmt->bindValue('endTs', $endTs, ParameterType::INTEGER);

        $arrResult = [];
        foreach( $stmt->executeQuery()->fetchAllKeyValue() as $day => $cnt ) {
            $arrResult[(string)$day] = (int)$cnt;
        }

        return $arrResult;
    }


    /**
     * Returns the top forum contributors in the inclusive range, ordered by post count desc:
     *   [ ['userId' => int, 'username' => string, 'usernameClean' => string, 'colour' => string, 'posts' => int], ... ]
     *
     * `colour` is the phpBB `user_colour` hex string (without leading #) — empty for users without a custom colour.
     */
    public function getTopPosters(\DateTimeInterface $start, \DateTimeInterface $end, int $limit = 10) : array
    {
        $startTs    = (int)$start->format('U');
        $endTs      = (int)$end->format('U') + 86399;

        $offlimits  = implode(',', array_map('intval', Forum::ID_OFFLIMIT));
        $usersTable = $this->arrConfig['forumDatabaseName'] . '.' . static::TABLE_PREFIX . 'users';

        $sql = "
            SELECT
                posts.poster_id     AS userId,
                users.username      AS username,
                users.username_clean AS usernameClean,
                users.user_colour   AS colour,
                COUNT(*)            AS posts
            FROM " . $this->getPhpBBTableName() . "
            INNER JOIN $usersTable AS users ON users.user_id = posts.poster_id
            WHERE
                posts.post_time BETWEEN :startTs AND :endTs AND
                posts.post_visibility = 1 AND
                posts.post_delete_time = 0 AND
                posts.forum_id NOT IN($offlimits) AND
                users.user_type IN(0, 3) AND
                posts.poster_id != 1
            GROUP BY posts.poster_id
            ORDER BY posts DESC
            LIMIT :lim
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('startTs', $startTs, ParameterType::INTEGER);
        $stmt->bindValue('endTs', $endTs, ParameterType::INTEGER);
        $stmt->bindValue('lim', $limit, ParameterType::INTEGER);

        $arrOut = [];
        foreach( $stmt->executeQuery()->fetchAllAssociative() as $row ) {

            // Only emit hex strings (e.g. AA0000); anything else gets blanked to avoid CSS injection in inline styles.
            $rawColour = trim( (string)($row['colour'] ?? '') );
            $colour    = preg_match('/^[0-9a-f]{3,8}$/i', $rawColour) ? $rawColour : '';

            $arrOut[] = [
                'userId'        => (int)$row['userId'],
                'username'      => (string)$row['username'],
                'usernameClean' => (string)$row['usernameClean'],
                'colour'        => $colour,
                'posts'         => (int)$row['posts']
            ];
        }

        return $arrOut;
    }
}
