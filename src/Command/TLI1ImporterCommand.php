<?php
namespace App\Command;

use App\Entity\Cms\Article as ArticleEntity;
use App\Entity\Cms\ArticleAuthor;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


#[AsCommand(
    name: 'tli1',
    description: 'Import data from TLI1 to TLI2',
)]
class TLI1ImporterCommand extends AbstractBaseCommand
{
    protected \PDO $dbTli1;
    protected UserRepository $repoUsers;
    protected array $arrUsersByContributionType = [];


    public function __construct(protected EntityManagerInterface $em)
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this
            ->fxTitle("Connecting to TLI1 DB...")
            ->tli1DbConnect()

            ->fxTitle("Loading Users from view...")
            ->loadUsers()

            ->fxTitle("Loading Authors from TLI1...")
            ->loadAuthors()

            ->fxTitle("Disable autoincrement on TLI2 (so we can preserve old IDs)...")
            ->disableAutoincrementOnTli2()

            ->fxTitle("Processing invalid TLI1 Pages...")
            ->processInvalidTli1Pages()

            ->fxTitle("Import Articles...")
            ->importArticles()

            //->fxTitle("Import Images...")
            //->importImages()

            //->fxTitle("Import Tags...")
            //->importTags()

            //->fxTitle("Import Files...")
            //->importFiles()

            ->fxTitle("Persisting...")
            ->em->flush();

        return $this->endWithSuccess();
    }


    protected function tli1DbConnect() : static
    {
        $arrDbConfig = $this->em->getConnection()->getParams();
        $dsn = "mysql:host=" . $arrDbConfig["host"] . ";dbname=tli1;charset=" . $arrDbConfig["charset"];

        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->dbTli1 = new \PDO($dsn, $arrDbConfig["user"], $arrDbConfig["password"], $options);

        return $this;
    }


    protected function loadUsers() : static
    {
        $this->repoUsers = $this->em->getRepository(User::class);
        $arrUsers = $this->repoUsers->loadAll();
        $this->fxOK(count($arrUsers) . " item(s) loaded");
        return $this;
    }


    protected function loadAuthors()
    {
        /**
         * TLI1 doesn't provide the author of the images uploaded after 2013 (???) =>
         * discard the whole "author of the image" data and
         * fall back to the "author of the article" as "article of the images it contains"
         */
        $stmt = $this->dbTli1->query("
            SELECT * FROM autori WHERE tipo != 'immagine'
            UNION
            SELECT id_utente, id_tag AS id_opera, 'tag' AS tipo, data_creazione AS data FROM tag
            ORDER BY data ASC
        ");
        $arrOldAuthors = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->fxOK( "OK, " . count($arrOldAuthors) . " author associations loaded!");

        $this->io->text("Building the authors data structure...");
        foreach($arrOldAuthors as $arrOldAuthor) {

            $userId         = $arrOldAuthor["id_utente"];
            $contributionId = $arrOldAuthor["id_opera"];
            $contribType    = $arrOldAuthor["tipo"];
            $createdAt      = \DateTime::createFromFormat('YmdHis', $arrOldAuthor["data"]);

            if( empty($createdAt) ) {
                return $this->endWithError("This author assignment has no date: " . print_r($arrOldAuthor, true));
            }

            $user = $this->repoUsers->selectOrNull($userId);
            if( empty($user) ) {
                continue;
            }

            $this->arrUsersByContributionType[$contribType][$contributionId][] = [
                "user"  => $user,
                "date"  => $createdAt
            ];
        }

        return $this;
    }


    protected function processInvalidTli1Pages() : static
    {
        $this->io->text("Removing pages with empty body from TLI1...");
        $this->dbTli1->exec("
            DELETE FROM pagine
            WHERE corpo IS NULL OR corpo = ''
        ");
        $this->fxOK();

        $this->io->text("Checking for dangling pages on TLI1...");
        $stmt = $this->dbTli1->query("
            SELECT pagine.id_pagina, contenuti.id_contenuto
            FROM pagine
            LEFT JOIN contenuti
            ON pagine.id_contenuto = contenuti.id_contenuto
            WHERE
            pagine.id_contenuto IS NULL OR pagine.id_contenuto = '' OR
            contenuti.id_contenuto IS NULL OR contenuti.id_contenuto = ''
        ");
        $arrInvalidPages = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        if( !empty($arrInvalidPages) ) {
            return $this->endWithError("There are dangling pages on TLI1: " . print_r($arrInvalidPages, true));
        }
        $this->fxOK();

        $this->io->text("Check for multiple pages relating to the same article on TLI1...");
        $stmt = $this->dbTli1->query("
            SELECT id_pagina, id_contenuto
            FROM pagine WHERE id_contenuto IN(
                SELECT id_contenuto
                FROM pagine
                GROUP BY id_contenuto
                HAVING COUNT(1) > 1
                )
            ORDER BY id_contenuto,id_pagina
        ");
        $arrInvalidPages = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        if( !empty($arrInvalidPages) ) {
            return $this->endWithError(
                "There are multiple pages relating to the same article on TLI1: " . print_r($arrInvalidPages, true)
            );
        }
        $this->fxOK();

        return $this;
    }


    protected function disableAutoincrementOnTli2() : static
    {
        foreach([ArticleEntity::class] as $className) {

            $this->em
                ->getClassMetadata($className)
                ->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        }

        return $this;
    }


    protected function importArticles()
    {
        $this->io->text("Loading TLI1 articles...");
        $stmt = $this->dbTli1->query("
            SELECT contenuti.id_contenuto AS pdokey, contenuti.*, pagine.corpo
            FROM contenuti
            LEFT JOIN pagine
            ON contenuti.id_contenuto = pagine.id_contenuto
            ORDER BY id_contenuto ASC
        ");
        $arrTli1Articles = $stmt->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
        $this->fxOK( count($arrTli1Articles) . " items loaded");

        $this->io->text("Loading TLI2 articles...");
        $arrTli2Articles = $this->em->getRepository(ArticleEntity::class)->loadAll();
        $this->fxOK( count($arrTli2Articles) . " item(s) loaded");
        unset($arrTli2Articles);

        //$this->io->text("Load comments (forum topics) from view...");
        //$repoComments = $this->em->getRepository(Topic::class)->loadWholeTable();

        $this->io->text("Processing every TLI1 article...");
        $this->processItems($arrTli1Articles, [$this, 'processTli1Article'], null, [$this, 'buildItemTitle']);

        return $this;
    }


    protected function processTli1Article(int $articleId, array $arrArticle)
    {
        $title          = $arrArticle["titolo"];
        $abstract       = $arrArticle["abstract"];
        $pubStatus      = match( $arrArticle["finito"] ) {
            0 => ArticleEntity::PUBLISHING_STATUS_DRAFT,
            1 => ArticleEntity::PUBLISHING_STATUS_READY_FOR_REVIEW
        };

        $views          = (int)$arrArticle["visite"];
        $format         = (int)$arrArticle["formato"];
        $rating         = (int)$arrArticle["rating"];
        $ads            = (bool)$arrArticle["ads"];
        //$commentsTopic  = $repoComments->selectOrNull($arrArticle["id_commenti_phpbb"]);
        $body           = $arrArticle["corpo"];
        $createdAt      = $arrArticle["data_creazione"] ?: null;
        $updatedAt      = $arrArticle["data_update"] ?: null;
        $publishedAt    = $arrArticle["data_pubblicazione"] ?: null;

        if( empty($createdAt) && empty($updatedAt) ) {
            return $this->endWithError("This article has no dates: " . print_r($arrArticle, true));
        }

        $createdAt  = $createdAt ?: $updatedAt ?: $publishedAt;
        $updatedAt  = $updatedAt ?: $publishedAt ?: $createdAt;
        $createdAt  = \DateTime::createFromFormat('YmdHis', $createdAt);
        $updatedAt  = \DateTime::createFromFormat('YmdHis', $updatedAt);

        if( $rating == -1 ) {

            $publishedAt    = null;
            $pubStatus      = ArticleEntity::PUBLISHING_STATUS_REMOVED;

        } else if( !empty($publishedAt) ) {

            $publishedAt    = \DateTime::createFromFormat('YmdHis', $publishedAt);
            $pubStatus      = ArticleEntity::PUBLISHING_STATUS_PUBLISHED;
        }

        /** @var ArticleEntity $entityTli2Article */
        $entityTli2Article =
            $this->em->getRepository(ArticleEntity::class)
                ->selectOrNew($articleId)
                    ->setTitle($title)
                    ->setFormat($format)
                    ->setPublishingStatus($pubStatus)
                    ->setShowAds($ads)
                    //->setCommentsTopic($commentsTopic)
                    ->setViews($views)
                    ->setAbstract($abstract)
                    ->setBody($body)
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt)
                    ->setPublishedAt($publishedAt);

        /*$spotlightId = $arrArticle["spotlight"];
        if( !empty($spotlightId) && $spotlightId != 1 ) {
            $this->arrSpotlightIds[$articleId] = $spotlightId;
        }*/

        // AUTHORS
        $arrTli1Authors = $this->arrUsersByContributionType["contenuto"][$articleId] ?? [];
        foreach($arrTli1Authors as $idx => $arrOldAuthorData) {

            $author =
                (new ArticleAuthor())
                    ->setUser( $arrOldAuthorData["user"] )
                    ->setCreatedAt( $arrOldAuthorData["date"] )
                    ->setUpdatedAt( $arrOldAuthorData["date"] )
                    ->setRanking( $idx + 1 );

            $entityTli2Article->addAuthor($author);
        }

        $this->em->persist($entityTli2Article);
        //$this->arrNewArticles[$articleId] = $entity;
    }
}
