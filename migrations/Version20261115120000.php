<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20261115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FULLTEXT index on file.title + file.url: the editor search finds an external Download by its URL too';
    }

    /**
     * title_fulltext_idx stays: the "similar Downloads" check matches the title alone.
     *
     * ALGORITHM=COPY rebuilds the table. An in-place ADD FULLTEXT INDEX restarts InnoDB's FULLTEXT document IDs
     * from the highest one still in the table: if the newest rows were deleted, the next new rows reuse their
     * IDs, which are still on the "deleted" list, so FULLTEXT never finds them (reproduced on MySQL 8.4.13)
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD FULLTEXT INDEX title_url_fulltext_idx (title, url), ALGORITHM=COPY');
    }


    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX title_url_fulltext_idx ON file');
    }
}
