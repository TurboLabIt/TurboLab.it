<?php
namespace App\Repository\Cms;

use App\Entity\Cms\File;
use App\Repository\BaseRepository;


class FileRepository extends BaseRepository
{
    const string ENTITY_CLASS = File::class;
    const int ORPHANS_AFTER_MONTHS          = ImageRepository::ORPHANS_AFTER_MONTHS;
    const int DELETE_ORPHANS_AFTER_MONTHS   = ImageRepository::DELETE_ORPHANS_AFTER_MONTHS;


    public function getByHash(array $arrHashes) : array
    {
        if( empty($arrHashes) ) {
            return [];
        }

        return
            $this->createQueryBuilder('t', 't.hash')
                ->andWhere( 't.hash IN(:hashes)')
                ->setParameter('hashes', $arrHashes)
                ->getQuery()->getResult();
    }


    public function getOrphans() : array
    {
        return
            $this->getQueryBuilder()
                ->leftJoin('t.articles', 'articlesJunction')
                ->where('articlesJunction.id IS NULL')
                ->getQuery()->getResult();
    }


    public function getFormats() : array
    {
        return
            $this->createQueryBuilder('f')
                ->select('f.format, COUNT(f.id) AS num')
                ->groupBy('f.format')
                ->orderBy('f.format', 'ASC')
            ->getQuery()->getResult();
    }


    public function getLatest(?int $authorId = null, int $limit = 10) : array
    {
        $qb =
            $this->getQueryBuilder()
                ->andWhere("t.format IS NOT NULL AND t.format != ''")
                ->orderBy('t.updatedAt', 'DESC')
                ->setMaxResults($limit);

        if( !empty($authorId) ) {

            $arrFileIds =
                $this->sqlQueryExecute(
                    "SELECT DISTINCT file_id FROM file_author WHERE user_id = :authorId",
                    ['authorId' => $authorId]
                )->fetchFirstColumn();

            if( empty($arrFileIds) ) {
                return [];
            }

            $qb->andWhere('t.id IN (:ids)')->setParameter('ids', $arrFileIds);
        }

        return $qb->getQuery()->getResult();
    }


    public function getSerpByFulltext(string $termToSearch, ?int $authorId = null, ?string $sort = null) : array
    {
        $termToSearch       = preg_replace('/[^a-z0-9_ ]/i', '', $termToSearch);
        $arrFulltextTerms   = [];
        $arrLikeTerms       = [];

        foreach(explode(' ', $termToSearch) as $term) {

            $term   = trim($term);
            $len    = mb_strlen($term);

            if( $len < 2 ) {
                continue;
            }

            if( $len >= 3 ) {

                $arrFulltextTerms[] = "+$term";

            } else {
                // Below innodb_ft_min_token_size (3) — MySQL would not index
                // the token, making "+token" unmatchable. Filter via LIKE.
                $arrLikeTerms[] = $term;
            }
        }

        if( empty($arrFulltextTerms) && empty($arrLikeTerms) ) {
            return [];
        }

        $sqlParams      = [];
        $whereClauses   = [];
        $rankingExpr    = '0';

        if( !empty($arrFulltextTerms) ) {

            $sqlParams['termToSearch']  = implode(' ', $arrFulltextTerms);
            $whereClauses[]             = "MATCH(f.title) AGAINST(:termToSearch IN BOOLEAN MODE)";
            $rankingExpr                = "MATCH(f.title) AGAINST(:termToSearch IN BOOLEAN MODE)";
        }

        foreach($arrLikeTerms as $i => $term) {

            $paramName              = "likeTerm$i";
            $whereClauses[]         = "f.title LIKE :$paramName";
            $sqlParams[$paramName]  = '%' . $term . '%';
        }

        $whereClauses[] = "f.format IS NOT NULL AND f.format != ''";

        $authorFilter = '';
        if( !empty($authorId) ) {
            $authorFilter           = "AND EXISTS (SELECT 1 FROM file_author fa WHERE fa.file_id = f.id AND fa.user_id = :authorId)";
            $sqlParams['authorId']  = $authorId;
        }

        $orderBy    = $sort === 'date' ? 'f.id DESC' : 'ranking DESC';
        $tableName  = $this->getTableName();
        $whereSql   = implode(' AND ', $whereClauses);

        $sqlSelect = "
            SELECT f.id, $rankingExpr AS ranking FROM $tableName AS f
            WHERE
                $whereSql
                $authorFilter
            ORDER BY $orderBy
            LIMIT 50
        ";

        $arrIds = $this->sqlQueryExecute($sqlSelect, $sqlParams)->fetchFirstColumn();
        if( empty($arrIds) ) {
            return [];
        }

        $arrFiles =
            $this->getQueryBuilder()
                ->andWhere('t.id IN (:ids)')
                ->setParameter("ids", $arrIds)
                ->getQuery()
                ->getResult();

        $arrReorder = array_flip($arrIds);
        foreach($arrReorder as $fileId => $value) {

            if( !array_key_exists($fileId, $arrFiles) ) {

                unset($arrReorder[$fileId]);
                continue;
            }

            $arrReorder[$fileId] = $arrFiles[$fileId];
        }

        return $arrReorder;
    }
}
