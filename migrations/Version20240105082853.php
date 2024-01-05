<?php declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20240105082853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('DROP TABLE IF EXISTS article');
        $this->addSql('SET foreign_key_checks = 1');
    }

    public function down(Schema $schema): void
    { }
}
