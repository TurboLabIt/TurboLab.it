<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260419000000 extends AbstractMigration
{
    public function getDescription(): string { return 'FULLTEXT index on file.title for the "link-file" editor search'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE FULLTEXT INDEX title_fulltext_idx ON file (title)');
    }


    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX title_fulltext_idx ON file');
    }
}
