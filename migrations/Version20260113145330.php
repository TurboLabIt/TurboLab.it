<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260113145330 extends AbstractMigration
{
    public function getDescription(): string { return 'ArticleGroup tables'; }


    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE article_group (id INT UNSIGNED AUTO_INCREMENT NOT NULL, group_name VARCHAR(50) NOT NULL, visible TINYINT(1) DEFAULT 1 NOT NULL, article_id INT UNSIGNED NOT NULL, ranking SMALLINT UNSIGNED DEFAULT 1 NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_1A7736D47294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('ALTER TABLE article_group ADD CONSTRAINT FK_1A7736D47294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('CREATE UNIQUE INDEX same_article_same_group_ranking_unique_idx ON article_group (group_name, article_id, ranking)');
    }


    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX same_article_same_group_ranking_unique_idx ON article_group');
        $this->addSql('ALTER TABLE article_group DROP FOREIGN KEY FK_1A7736D47294869C');
        $this->addSql('DROP TABLE article_group');
    }
}
