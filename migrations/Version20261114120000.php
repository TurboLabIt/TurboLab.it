<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20261114120000 extends AbstractMigration
{
    public function getDescription() : string { return 'file.source_updated_at: when the file or the URL last changed, shown to readers'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD source_updated_at DATETIME DEFAULT NULL AFTER hash');

        // a local file could never be replaced before this column existed: its content dates from its creation.
        // An external URL could be edited: updated_at is the closest date available (a rename moves it too)
        $this->addSql("UPDATE file SET source_updated_at = IF(url IS NULL OR url = '', created_at, updated_at)");

        $this->addSql('ALTER TABLE file MODIFY source_updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP source_updated_at');
    }
}
