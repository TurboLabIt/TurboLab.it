<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260403105146 extends AbstractMigration
{
    public function getDescription(): string { return ''; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE article_badge (id INT UNSIGNED AUTO_INCREMENT NOT NULL, article_id INT UNSIGNED NOT NULL, badge_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_89473A0C7294869C (article_id), INDEX IDX_89473A0CF7A2C2FC (badge_id), INDEX IDX_89473A0CA76ED395 (user_id), UNIQUE INDEX same_article_same_badge_unique_idx (article_id, badge_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE article_badge ADD CONSTRAINT FK_89473A0C7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_badge ADD CONSTRAINT FK_89473A0CF7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE');

        $this->addSql("INSERT INTO badge (title,image_url,user_selectable,abstract,body,created_at,updated_at) VALUES
	 ('ai','cyborg-brain-human-machine-tiny.png',1,'Autore umano, supporto AI','Gli articoli di TurboLab.it sono curati dagli utenti della nostra community, ma possono essere <a href=\"/4524\">generati o migliorati tramite intelligenza artificiale</a>', NOW(), NOW());
");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_badge DROP FOREIGN KEY FK_89473A0C7294869C');
        $this->addSql('ALTER TABLE article_badge DROP FOREIGN KEY FK_89473A0CF7A2C2FC');
        $this->addSql('DROP TABLE article_badge');
    }
}
