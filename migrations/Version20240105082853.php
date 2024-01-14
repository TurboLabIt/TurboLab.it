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
        $this->addSql('DROP TABLE IF EXISTS article_author');
        $this->addSql('DROP TABLE IF EXISTS article_file');
        $this->addSql('DROP TABLE IF EXISTS article_image');
        $this->addSql('DROP TABLE IF EXISTS article_tag');
        $this->addSql('DROP TABLE IF EXISTS file');
        $this->addSql('DROP TABLE IF EXISTS file_author');
        $this->addSql('DROP TABLE IF EXISTS image');
        $this->addSql('DROP TABLE IF EXISTS image_author');
        $this->addSql('DROP TABLE IF EXISTS tag');
        $this->addSql('DROP TABLE IF EXISTS tag_author');
        $this->addSql('DROP VIEW IF EXISTS user');
        $this->addSql('SET foreign_key_checks = 1');

        // https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/users.md
        $this->addSql("
            CREATE VIEW turbolab_it.user AS
            SELECT
              phpbb_users.user_id AS id, user_type AS type, username, pf_tli_fullname AS fullname,
              user_email AS email, user_posts AS posts_num,
              user_avatar AS avatar, user_avatar_type AS avatar_type,
              user_colour AS color, user_allow_massemail AS accept_emails,
              pf_tli_bio AS bio,
              ## this is a dummy field required by Symfony
              '[\"ROLE_USER\"]' AS roles
            FROM
              turbolab_it_forum.phpbb_users
            LEFT JOIN
              (SELECT user_id, pf_tli_fullname FROM turbolab_it_forum.phpbb_profile_fields_data) AS phpbb_fullnames
            ON
              phpbb_users.user_id = phpbb_fullnames.user_id
            LEFT JOIN
              (SELECT user_id, pf_tli_bio FROM turbolab_it_forum.phpbb_profile_fields_data) AS phpbb_bios
            ON
              phpbb_users.user_id = phpbb_bios.user_id
            WHERE
              ## excluding inactive,bots
              user_type NOT IN(1,2)
            ORDER BY
                phpbb_users.user_id
        ");

        //
        /*$this->addSql("
            CREATE VIEW turbolab_it.post AS
            SELECT
                post_id AS id, topic_id, post_time, post_subject AS subject,
                poster_id AS user_id
            FROM
                turbolab_it_forum.phpbb_posts
            WHERE
                post_reported = 0 AND
                post_visibility = 1 AND
                topic_id IN(SELECT DISTINCT id FROM turbolab_it.topic)
            ORDER BY
                post_time DESC
        ");*/
    }

    public function down(Schema $schema): void
    { }
}
