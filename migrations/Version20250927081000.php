<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20250927081000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD comments_topic_needs_update SMALLINT UNSIGNED DEFAULT 0 NOT NULL AFTER comments_topic_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP comments_topic_needs_update');
    }
}
