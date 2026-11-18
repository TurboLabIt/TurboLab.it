<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20261119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'image.orphaned_at: when the image lost its last article, the date ImagesDelete counts its months from';
    }

    /**
     * Empty for every image: the next ImagesDelete run (ImageRepository::updateOrphanedAt()) dates the orphans of today
     * too, so each of them gets its whole year from the deploy on
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image ADD orphaned_at DATETIME DEFAULT NULL');
    }


    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image DROP orphaned_at');
    }


    // prevent User Deprecated: Context: trying to commit a transaction Problem: the transaction is already committed, relying on silencing is deprecated
    public function isTransactional(): bool { return false; }
}
