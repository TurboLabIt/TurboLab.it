<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20250917170154 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bug (id INT UNSIGNED AUTO_INCREMENT NOT NULL, remote_id VARCHAR(25) DEFAULT NULL, remote_url VARCHAR(1024) DEFAULT NULL, post_id INT UNSIGNED DEFAULT NULL, user_id INT UNSIGNED DEFAULT NULL, user_ip_address VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bug');
    }
}
