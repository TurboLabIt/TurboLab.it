<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20261006120000 extends AbstractMigration
{
    public function getDescription() : string {  return ''; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image ADD animated TINYINT DEFAULT 0 NOT NULL AFTER format');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image DROP animated');
    }
}
