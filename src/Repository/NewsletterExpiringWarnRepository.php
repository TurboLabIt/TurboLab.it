<?php
namespace App\Repository;

use App\Entity\NewsletterExpiringWarn;
use Doctrine\DBAL\Result;


class NewsletterExpiringWarnRepository extends BaseRepository
{
    const string ENTITY_CLASS = NewsletterExpiringWarn::class;


    public function deleteByUserId(int $userId) : Result
    {
        $sqlQuery = "DELETE FROM " . $this->getTableName() . " WHERE user_id = :userId";
        return $this->sqlQueryExecute($sqlQuery, ["userId" => $userId]);
    }
}
