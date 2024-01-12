<?php
namespace App\Command;

use App\Entity\Cms\Article as ArticleEntity;
use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleImage;
use App\Entity\Cms\Image as ImageEntity;
use App\Entity\Cms\ImageAuthor;
use App\Entity\PhpBB\Topic;
use App\Entity\User;
use App\Repository\PhpBB\TopicRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


#[AsCommand(name: 'TLI1 Importer', description: 'Import data from TLI1 to TLI2', aliases: ['tli1'])]
class TLI1ImporterCommand extends AbstractBaseCommand
{
    protected \PDO $dbTli1;
    protected UserRepository $repoUsers;
    protected array $arrAuthorsByContributionType = [];
    protected TopicRepository $repoTopics;
    protected array $arrNewArticles = [];
    protected array $arrNewImages = [];


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

            ->fxTitle("Loading Comment Topics...")
            ->loadCommentTopics()

            ->fxTitle("Disable autoincrement on TLI2 (so we can preserve old IDs)...")
            ->disableAutoincrementOnTli2()

            ->fxTitle("Processing invalid TLI1 Pages...")
            ->processInvalidTli1Pages()

            ->fxTitle("Importing Articles...")
            ->importArticles()

            ->fxTitle("Import Images...")
            ->importImages()

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

            $this->arrAuthorsByContributionType[$contribType][$contributionId][] = [
                "user"  => $user,
                "date"  => $createdAt
            ];
        }

        return $this;
    }


    protected function loadCommentTopics() : static
    {
        $this->repoTopics = $this->em->getRepository(Topic::class);
        $arrTopics = $this->repoTopics->loadAll();
        $this->fxOK(count($arrTopics) . " item(s) loaded");
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
        foreach([ArticleEntity::class, ImageEntity::class] as $className) {

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
        $commentsTopic  = $this->repoTopics->selectOrNull($arrArticle["id_commenti_phpbb"]);
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
                    ->setPublishedAt($publishedAt)
                    ->setShowAds($ads)
                    ->setCommentsTopic($commentsTopic)
                    ->setViews($views)
                    ->setAbstract($abstract)
                    ->setBody($body)
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt);

        /*$spotlightId = $arrArticle["spotlight"];
        if( !empty($spotlightId) && $spotlightId != 1 ) {
            $this->arrSpotlightIds[$articleId] = $spotlightId;
        }*/

        // AUTHORS
        $arrTli1Authors = $this->arrAuthorsByContributionType["contenuto"][$articleId] ?? [];
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
        $this->arrNewArticles[$articleId] = $entityTli2Article;
    }


    protected function importImages() : static
    {
        $this->io->text("Loading TLI1 images...");
        $stmt = $this->dbTli1->query("SELECT id_immagine AS pdokey, immagini.* FROM immagini ORDER BY id_immagine ASC");
        $arrTli1Images = $stmt->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
        $this->fxOK( count($arrTli1Images) . " items loaded");

        $this->io->text("Loading TLI2 images...");
        $arrTli2Images = $this->em->getRepository(ImageEntity::class)->loadAll();
        $this->fxOK( count($arrTli2Images) . " item(s) loaded");
        unset($arrTli2Images);

        $this->io->text("Processing every TLI2 image...");
        $this->processItems($arrTli1Images, [$this, 'processTli1Image'], null, [$this, 'buildItemTitle']);

        return $this;
    }


    protected function processTli1Image(int $imageId, array $arrImage)
    {
        $title = $arrImage["titolo"];
        $format = mb_strtolower($arrImage["formato"]);
        $createdAt = \DateTime::createFromFormat('YmdHis', $arrImage["data_creazione"]);
        $watermark = match ($arrImage["watermarked"]) {
            0 => ImageEntity::WATERMARK_DISABLED,
            1 => ImageEntity::WATERMARK_BOTTOM_RIGHT
        };

        if (!in_array($format, ['png', 'jpg'])) {
            return $this->endWithError(
                "This is not a png/jpg image: " . print_r($arrImage, true)
            );
        }

        /** @var ImageEntity $entityTli2Image */
        $entityTli2Image =
            $this->em->getRepository(ImageEntity::class)
                ->selectOrNew($imageId)
                ->setTitle($title)
                ->setFormat($format)
                ->setWatermarkPosition($watermark)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($createdAt);

        // LINK TO ARTICLE
        $articleId = $arrImage["id_opera"];
        /** @var ArticleEntity $article */
        $article = $this->arrNewArticles[$articleId] ?? null;

        if (empty($article)) {
            return $this->endWithError(
                "No related article: " . print_r($arrImage, true)
            );
        }

        $imageArticleLink =
            (new ArticleImage())
                ->setArticle($article)
                ->setCreatedAt( $article->getCreatedAt() )
                ->setUpdatedAt( $article->getUpdatedAt() );

        $entityTli2Image->addArticle($imageArticleLink);

        // AUTHORS
        $arrArticleAuthors = $article->getAuthors();
        foreach ($arrArticleAuthors as $idx => $articleAuthor) {

            $imageAuthor =
                (new ImageAuthor())
                    ->setUser($articleAuthor->getUser())
                    ->setCreatedAt($articleAuthor->getCreatedAt())
                    ->setUpdatedAt($articleAuthor->getUpdatedAt())
                    ->setRanking($idx + 1);

            $entityTli2Image->addAuthor($imageAuthor);
        }

        $this->em->persist($entityTli2Image);
        $this->arrNewImages[$imageId] = $entityTli2Image;
    }


    protected function assignSpotlights() : static
    {
        $this->io->text("Adding the spotlight to each article...");
        $progressBar = new ProgressBar($this->output, count($this->arrNewArticles));
        $progressBar->start();

        foreach($this->arrNewArticles as $articleId => $article) {

            $spotlightId =
                empty($this->arrSpotlightIds[$articleId]) ? null : $this->arrSpotlightIds[$articleId];

            $spotlight =
                empty($arrNewImages[$spotlightId]) ? null : $arrNewImages[$spotlightId];

            if( !empty($spotlight) ) {

                $article->setSpotlight($spotlight);
                $this->em->persist($article);

                $imageLoadedInArticleId = $article->getId();
                $arrImagesLoadedIn[$spotlightId][$imageLoadedInArticleId] = $this->arrNewArticles[$imageLoadedInArticleId];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine(2);


        $this->io->text("Loading new images attached to articles...");
        $repoAttachs = $this->em->getRepository(ImageArticle::class)->loadWholeTable();

        $this->io->text("Attaching each image to the releated article...");
        $progressBar = new ProgressBar($this->output, count($arrNewImages));
        $progressBar->start();

        foreach($arrNewImages as $imageId => $image) {

            $arrArticles =  $arrImagesLoadedIn[$imageId];

            if( empty($arrArticles) ) {

                throw new \Exception("Dangling image!");
            }

            foreach($arrArticles as $article) {

                $entityAttach =
                    $repoAttachs->selectOrNew($image, $article)
                        ->setCreatedAt($image->getCreatedAt())
                        ->setUpdatedAt($image->getUpdatedAt());

                $this->em->persist($entityAttach);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine(2);


        $this->io->text("Loading new images authors...");
        $repoAuthors = $this->em->getRepository(ImageAuthor::class)->loadWholeTable();

        $this->io->text("Adding authors to each image...");
        $progressBar = new ProgressBar($this->output, count($arrNewImages));
        $progressBar->start();

        // image authors are missing after 2013 => fetching images authors from article authors

        foreach($arrNewImages as $imageId => $image) {

            $arrArticles = $arrImagesLoadedIn[$imageId];
            foreach($arrArticles as $articleId => $article) {

                $arrAuthorsData = $this->arrAuthorsByContributionType["contenuto"][$articleId];
                foreach($arrAuthorsData as $idx => $arrAuthorData) {

                    $entityUser = $arrAuthorData["user"];
                    $createdAt  = $arrAuthorData["date"];

                    $imageAuthor  =
                        $repoAuthors->selectOrNew($image, $entityUser)
                            ->setCreatedAt($createdAt)
                            ->setUpdatedAt($createdAt)
                            ->setPriority($idx);

                    $this->em->persist($imageAuthor);
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine(2);
    }
}
