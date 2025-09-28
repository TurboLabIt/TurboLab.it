<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20250928210818 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY `FK_23A0E663049EF9`');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E663049EF9 FOREIGN KEY (spotlight_id) REFERENCES image (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E663049EF9');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT `FK_23A0E663049EF9` FOREIGN KEY (spotlight_id) REFERENCES image (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
