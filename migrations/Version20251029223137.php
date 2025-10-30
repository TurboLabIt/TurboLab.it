<?php declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20251029223137 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE article_file SET file_id = 69 WHERE file_id = 116');
        $this->addSql("
            UPDATE file f1
            JOIN file f2 ON f2.id = 116
            SET f1.views = f1.views + f2.views
            WHERE f1.id = 69
        ");
        $this->addSql("DELETE FROM file WHERE id = 116");
        $this->addSql("UPDATE file SET title = 'Mostra o nascondi aggiornamenti' WHERE id = 69");

        $this->addSql('ALTER TABLE file ADD hash CHAR(32) NOT NULL AFTER views');

        $this->addSql('UPDATE `file` SET `hash` = `id` WHERE url IS NULL');
        $this->addSql('UPDATE `file` SET `hash` = MD5(`url`) WHERE url IS NOT NULL');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_8C9F3610D1B862B8 ON file (hash)');
    }


    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8C9F3610D1B862B8 ON file');
        $this->addSql('ALTER TABLE file DROP hash');
    }


    // prevent User Deprecated: Context: trying to commit a transaction Problem: the transaction is already committed, relying on silencing is deprecated
    public function isTransactional(): bool { return false; }
}
