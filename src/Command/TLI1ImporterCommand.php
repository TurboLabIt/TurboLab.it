<?php
namespace App\Command;

use App\Entity\Cms\Article as ArticleEntity;
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

            //->fxTitle("Load Users from view...")
            //->loadUsers()

            ->fxTitle("Disable autoincrement on TLI2 (so that we can import old IDs...")
            ->disableAutoincrementOnTli2()

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
        // TODO check for pagine without contenuto, report and stop exec if any
        // TODO check for multiple pagine relating to one contenuto, report and stop exec if any

        $this->io->text("Loading TLI1 articles...");
        // https://turbolab.it/1939 , https://turbolab.it/2125
        $arrMalformedTli1Articles = [1939,2125];
        $stmt = $this->dbTli1->query('
            SELECT contenuti.id_contenuto AS pdokey, contenuti.*, pagine.corpo
            FROM contenuti
            LEFT JOIN pagine
            ON contenuti.id_contenuto = pagine.id_contenuto
            WHERE contenuti.id_contenuto NOT IN(' . implode(',', $arrMalformedTli1Articles) . ') ORDER BY id_contenuto ASC'
        );
        $arrTli1Articles = $stmt->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
        $this->fxOK( count($arrTli1Articles) . " items loaded!");

        $this->io->text("Loading TLI2 articles...");
        $arrTli2Articles = $this->em->getRepository(ArticleEntity::class)->loadAll();
        $this->fxOK( count($arrTli2Articles) . " item(s) loaded!");
        unset($arrTli2Articles);

        //$this->io->text("Loading new article authors...");
        //$repoAuthors = $this->em->getRepository(ArticleAuthor::class)->loadWholeTable();

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
        $pubStatus      = match($arrArticle["finito"]) {
            0   => ArticleEntity::PUBLISHING_STATUS_DRAFT,
            1   => ArticleEntity::PUBLISHING_STATUS_READY_FOR_REVIEW
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
            throw new \Exception("This article has no dates");
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

        /*$arrAuthors = $this->arrUsersByContributionType["contenuto"][$articleId];
        foreach($arrAuthors as $idx => $arrOldAuthorData) {

            $entityUser     = $arrOldAuthorData["user"];
            $createdAt      = $arrOldAuthorData["date"];

            $author  =
                $repoAuthors->selectOrNew($entity, $entityUser)
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($createdAt)
                    ->setPriority($idx);

            $this->em->persist($author);
        }*/

        $this->em->persist($entityTli2Article);
        //$this->arrNewArticles[$articleId] = $entity;
    }
}
