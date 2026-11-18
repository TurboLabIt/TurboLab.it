<?php
namespace App\Repository\Cms;

use App\Entity\Cms\ArticleImage;
use App\Entity\Cms\Image;
use App\Repository\BaseRepository;
use DateTime;
use Doctrine\ORM\QueryBuilder;
use SortDirection;


class ImageRepository extends BaseRepository
{
    const string ENTITY_CLASS               = Image::class;
    const int ORPHANS_AFTER_MONTHS          = 9;
    const int DELETE_ORPHANS_AFTER_MONTHS   = 12;

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


    public function getOrphans() : array { return $this->getQueryBuilderOrphans()->getQuery()->getResult(); }


    /**
     * The orphans that lost their last article DELETE_ORPHANS_AFTER_MONTHS ago (orphanedAt, see updateOrphanedAt()):
     * not their last change, which can be years older
     */
    public function getToDelete() : array
    {
        return
            $this->getQueryBuilderOrphans()
                ->andWhere('t.orphanedAt < :limitDate')
                ->setParameter('limitDate', new DateTime('-' . static::DELETE_ORPHANS_AFTER_MONTHS . ' months') )
                ->getQuery()->getResult();
    }


    /**
     * Dates (orphanedAt: now) each image that lost its last article since the previous run, and clears the date of each
     * one that is back in an article. A sweep of the junctions, rather than a hook wherever an image can lose its last
     * one, because a junction also goes with its article, removed by Doctrine's cascade or by the database's own
     * ON DELETE CASCADE. Bulk updates: the images' updatedAt doesn't change
     *
     * @return array{orphaned: int, adopted: int}
     */
    public function updateOrphanedAt() : array
    {
        $usedImageIds = 'SELECT IDENTITY(junction.image) FROM ' . ArticleImage::class . ' junction';

        $orphaned =
            $this->getEntityManager()->createQueryBuilder()
                ->update(Image::class, 't')
                ->set('t.orphanedAt', ':now')
                ->where('t.orphanedAt IS NULL')
                ->andWhere("t.id NOT IN($usedImageIds)")
                ->setParameter('now', new DateTime())
                ->getQuery()->execute();

        $adopted =
            $this->getEntityManager()->createQueryBuilder()
                ->update(Image::class, 't')
                ->set('t.orphanedAt', 'NULL')
                ->where('t.orphanedAt IS NOT NULL')
                ->andWhere("t.id IN($usedImageIds)")
                ->getQuery()->execute();

        return ['orphaned' => $orphaned, 'adopted' => $adopted];
    }


    /** by deletion date: the oldest orphanedAt first, then the orphans updateOrphanedAt() hasn't dated yet */
    protected function getQueryBuilderOrphans() : QueryBuilder
    {
        return
            $this->getQueryBuilder()
                ->leftJoin('t.articles', 'junction')
                ->where('junction.id IS NULL')
                ->orderBy('CASE WHEN t.orphanedAt IS NULL THEN 1 ELSE 0 END', SortDirection::Ascending)
                ->addOrderBy('t.orphanedAt', SortDirection::Ascending)
                ->addOrderBy('t.id', SortDirection::Ascending);
    }
}
