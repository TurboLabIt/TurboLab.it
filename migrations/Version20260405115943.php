<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260405115943 extends AbstractMigration
{
    public function getDescription(): string { return 'Re-sync database with ORM'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_358CBF14A76ED395 ON bug (user_id)');
        $this->addSql('CREATE INDEX IDX_358CBF144B89032C ON bug (post_id)');
        $this->addSql('ALTER TABLE file_author DROP FOREIGN KEY `FK_5B8FE7793CB796C`');
        $this->addSql('ALTER TABLE file_author ADD CONSTRAINT FK_5B8FE7793CB796C FOREIGN KEY (file_id) REFERENCES file (id)');
        $this->addSql('ALTER TABLE image_author DROP FOREIGN KEY `FK_12B286003DA5256D`');
        $this->addSql('ALTER TABLE image_author ADD CONSTRAINT FK_12B286003DA5256D FOREIGN KEY (image_id) REFERENCES image (id)');
        $this->addSql('ALTER TABLE tag_author DROP FOREIGN KEY `FK_54EE91E4BAD26311`');
        $this->addSql('ALTER TABLE tag_author ADD CONSTRAINT FK_54EE91E4BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id)');
        $this->addSql('ALTER TABLE tag_badge DROP FOREIGN KEY `FK_DC1C511BBAD26311`');
        $this->addSql('ALTER TABLE tag_badge DROP FOREIGN KEY `FK_DC1C511BF7A2C2FC`');
        $this->addSql('ALTER TABLE tag_badge ADD CONSTRAINT FK_DC1C511BBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id)');
        $this->addSql('ALTER TABLE tag_badge ADD CONSTRAINT FK_DC1C511BF7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id)');
        $this->addSql('ALTER TABLE visit RENAME INDEX idx_c12294787294869c TO IDX_437EE9397294869C');
        $this->addSql('ALTER TABLE visit RENAME INDEX idx_c1229478bad26311 TO IDX_437EE939BAD26311');
        $this->addSql('ALTER TABLE visit RENAME INDEX idx_c122947893cb796c TO IDX_437EE93993CB796C');
        $this->addSql('ALTER TABLE visit RENAME INDEX idx_c1229478a76ed395 TO IDX_437EE939A76ED395');
    }


    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_358CBF14A76ED395 ON bug');
        $this->addSql('DROP INDEX IDX_358CBF144B89032C ON bug');
        $this->addSql('ALTER TABLE file_author DROP FOREIGN KEY FK_5B8FE7793CB796C');
        $this->addSql('ALTER TABLE file_author ADD CONSTRAINT `FK_5B8FE7793CB796C` FOREIGN KEY (file_id) REFERENCES file (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE image_author DROP FOREIGN KEY FK_12B286003DA5256D');
        $this->addSql('ALTER TABLE image_author ADD CONSTRAINT `FK_12B286003DA5256D` FOREIGN KEY (image_id) REFERENCES image (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag_author DROP FOREIGN KEY FK_54EE91E4BAD26311');
        $this->addSql('ALTER TABLE tag_author ADD CONSTRAINT `FK_54EE91E4BAD26311` FOREIGN KEY (tag_id) REFERENCES tag (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag_badge DROP FOREIGN KEY FK_DC1C511BBAD26311');
        $this->addSql('ALTER TABLE tag_badge DROP FOREIGN KEY FK_DC1C511BF7A2C2FC');
        $this->addSql('ALTER TABLE tag_badge ADD CONSTRAINT `FK_DC1C511BBAD26311` FOREIGN KEY (tag_id) REFERENCES tag (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag_badge ADD CONSTRAINT `FK_DC1C511BF7A2C2FC` FOREIGN KEY (badge_id) REFERENCES badge (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visit RENAME INDEX idx_437ee9397294869c TO IDX_C12294787294869C');
        $this->addSql('ALTER TABLE visit RENAME INDEX idx_437ee93993cb796c TO IDX_C122947893CB796C');
        $this->addSql('ALTER TABLE visit RENAME INDEX idx_437ee939a76ed395 TO IDX_C1229478A76ED395');
        $this->addSql('ALTER TABLE visit RENAME INDEX idx_437ee939bad26311 TO IDX_C1229478BAD26311');
    }
}
