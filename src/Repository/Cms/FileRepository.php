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


    public function getSerpByFulltext(string $termToSearch, ?int $authorId = null, ?string $sort = null) : array
    {
        $termToSearch   = preg_replace('/[^a-z0-9_ ]/i', '', $termToSearch);
        $arrTerms       = explode(' ', $termToSearch);

        foreach($arrTerms as $k => $term) {

            $term = trim($term);

            if( empty($term) || mb_strlen($term) < 2 ) {

                unset($arrTerms[$k]);
                continue;
            }

            $arrTerms[$k] = "+$term";
        }

        $termToSearchPrepared = implode(' ', $arrTerms);
        if( empty($termToSearchPrepared) ) {
            return [];
        }

        $sqlParams      = ['termToSearch' => $termToSearchPrepared];
        $authorFilter   = '';

        if( !empty($authorId) ) {
            $authorFilter           = "AND EXISTS (SELECT 1 FROM file_author fa WHERE fa.file_id = f.id AND fa.user_id = :authorId)";
            $sqlParams['authorId']  = $authorId;
        }

        $orderBy    = $sort === 'date' ? 'f.id DESC' : 'ranking DESC';
        $tableName  = $this->getTableName();

        $sqlSelect = "
            SELECT f.id, MATCH(f.title) AGAINST(:termToSearch IN BOOLEAN MODE) AS ranking FROM $tableName AS f
            WHERE
                MATCH(f.title) AGAINST(:termToSearch IN BOOLEAN MODE)
                AND f.format IS NOT NULL AND f.format != ''
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
