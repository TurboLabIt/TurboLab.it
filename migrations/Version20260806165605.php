<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260806165605 extends AbstractMigration
{
    public function getDescription() : string {  return ''; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD allow_extended_html TINYINT DEFAULT 0 NOT NULL AFTER show_ads');
        $this->addSql('UPDATE article SET allow_extended_html = 1 WHERE id IN(4057,4142)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP allow_extended_html');
    }
}
