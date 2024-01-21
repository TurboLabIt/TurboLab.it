<?php
namespace App\Command;

use App\Entity\Cms\Article as ArticleEntity;
use App\Entity\Cms\ArticleAuthor;
use App\Entity\Cms\ArticleFile;
use App\Entity\Cms\ArticleImage;
use App\Entity\Cms\ArticleTag;
use App\Entity\Cms\Badge as BadgeEntity;
use App\Entity\Cms\File as FileEntity;
use App\Entity\Cms\FileAuthor;
use App\Entity\Cms\Image as ImageEntity;
use App\Entity\Cms\ImageAuthor;
use App\Entity\Cms\Tag as TagEntity;
use App\Entity\Cms\TagAuthor;
use App\Entity\Cms\TagBadge;
use App\Entity\PhpBB\Topic;
use App\Entity\PhpBB\User;
use App\Factory\Cms\ImageFactory;
use App\Repository\PhpBB\TopicRepository;
use App\Repository\PhpBB\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use TurboLabIt\BaseCommand\Service\ProjectDir;


#[AsCommand(name: 'TLI1 Importer', description: 'Import data from TLI1 to TLI2', aliases: ['tli1'])]
class TLI1ImporterCommand extends AbstractBaseCommand
{
    const OPT_SKIP_ARTICLES = "skip-articles";
    const OPT_SKIP_IMAGES   = "skip-images";
    const OPT_SKIP_TAGS     = "skip-tags";
    const OPT_SKIP_FILES    = "skip-files";
    const OPT_SKIP_BADGES   = "skip-badges";

    protected bool $allowDryRunOpt = true;

    protected \PDO $dbTli1;
    protected UserRepository $repoUsers;
    protected TopicRepository $repoTopics;

    protected array $arrAuthorsByContributionType = [];

    protected array $arrNewArticles     = [];
    protected array $arrNewImages       = [];
    protected array $arrSpotlightIds    = [];
    protected array $arrNewTags         = [];
    protected array $arrNewFiles        = [];
    protected array $arrNewBadges       = [];


    public function __construct(
        protected EntityManagerInterface $em, protected ProjectDir $projectDir,
        protected ImageFactory $imageFactory
    )
    {
        parent::__construct();
    }


    protected function configure() : void
    {
        parent::configure();
        foreach([
                static::OPT_SKIP_ARTICLES, static::OPT_SKIP_IMAGES, static::OPT_SKIP_TAGS, static::OPT_SKIP_FILES,
                static::OPT_SKIP_BADGES
            ] as $name) {
            $this->addOption($name, null, InputOption::VALUE_NONE);
        }
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this
            ->fxTitle("Connecting to TLI1 DB...")
            ->tli1DbConnect()

            ->fxTitle("Loading Users...")
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

            ->fxTitle("Importing Images...")
            ->importImages()

            ->fxTitle("Importing Tags...")
            ->importTags()

            ->fxTitle("Processing invalid TLI1 Tags Associations...")
            ->processInvalidTli1TagAssoc()

            ->fxTitle("Tagging Articles...")
            ->tagArticles()

            ->fxTitle("Importing Files...")
            ->importFiles()

            ->fxTitle("Processing invalid TLI1 Files Associations...")
            ->processInvalidTli1FileAssoc()

            ->fxTitle("Linking Files to Articles...")
            ->linkFilesAndArticles()

            ->fxTitle("Importing Badges...")
            ->importBadges()

            ->fxTitle("Badging Tags...")
            ->tagBadges()

            ->fxTitle("Persisting...");

        if( $this->isNotDryRun() ) {
            $this->em->flush();
        }

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

        return $this->fxOK(count($arrUsers) . " item(s) loaded");
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
                return $this->endWithError("This author assignment has no date: " . print_r($arrOldAuthor, true) );
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
        if( $this->getCliOption(static::OPT_SKIP_ARTICLES) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

        $this->repoTopics = $this->em->getRepository(Topic::class);
        $arrTopics = $this->repoTopics->loadAll();

        return $this->fxOK(count($arrTopics) . " item(s) loaded");
    }


    protected function processInvalidTli1Pages() : static
    {
        if( $this->getCliOption(static::OPT_SKIP_ARTICLES) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

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
            return $this->endWithError("There are dangling pages on TLI1: " . print_r($arrInvalidPages, true) );
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

        return $this->fxOK();
    }


    protected function disableAutoincrementOnTli2() : static
    {
        foreach([
            ArticleEntity::class, ImageEntity::class,
            TagEntity::class, FileEntity::class, BadgeEntity::class
            ] as $className) {

            $this->em
                ->getClassMetadata($className)
                ->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        }

        return $this;
    }


    protected function importArticles()
    {
        if( $this->getCliOption(static::OPT_SKIP_ARTICLES) ) {

            $this->arrNewArticles = $this->em->getRepository(ArticleEntity::class)->loadAll();
            return $this->fxWarning('🦘 Skipped!');
        }

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
            $this->endWithError("This article has no dates: " . print_r($arrArticle, true) );
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

        // this will be handled later on
        $spotlightId = $arrArticle["spotlight"];
        if( !empty($spotlightId) && $spotlightId != 1 ) {
            $this->arrSpotlightIds[$articleId] = $spotlightId;
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

        // AUTHORS
        $arrTli1Authors = $this->arrAuthorsByContributionType["contenuto"][$articleId] ?? [];
        if( empty($arrTli1Authors) ) {
            $this->fxWarning("This Article has no Authors: " . print_r($arrArticle, true) );
        }

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
        if( $this->getCliOption(static::OPT_SKIP_IMAGES) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

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

        $this
            ->fxTitle("Assigning the cover image to each article...")
            ->processItems($this->arrNewArticles, [$this, 'assignSpotlight'], null, function(){ return '';} );

        return $this;
    }


    protected function processTli1Image(int $imageId, array $arrImage)
    {
        $title = $arrImage["titolo"];
        $format = mb_strtolower($arrImage["formato"]);
        $createdAt = \DateTime::createFromFormat('YmdHis', $arrImage["data_creazione"]);
        $watermark = match ($arrImage["watermarked"]) {
            0 => ImageEntity::WATERMARK_DISABLED,
            1 => ImageEntity::WATERMARK_BOTTOM_LEFT
        };

        if (!in_array($format, ['png', 'jpg'])) {
            return $this->endWithError("This is not a png/jpg image: " . print_r($arrImage, true) );
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

        if ( empty($article) ) {
            return $this->endWithError("No related article: " . print_r($arrImage, true) );
        }

        $articleCreatedAt = $article->getCreatedAt();

        $imageArticleLink =
            (new ArticleImage())
                ->setArticle($article)
                ->setCreatedAt($articleCreatedAt)
                ->setUpdatedAt($articleCreatedAt);

        $entityTli2Image->addArticle($imageArticleLink);

        // IMAGE AUTHOR(S)
        $arrArticleAuthors = $article->getAuthors();
        if( empty($arrArticleAuthors->first()) ) {
            $this->fxWarning(
                "This Article has no Authors to transfer to the the image: " . print_r([
                    "article_id"    => $article->getId(),
                    "image_id"      => $imageId
                ], true)
            );
        }

        foreach ($arrArticleAuthors as $idx => $articleAuthor) {

            $imageAuthor =
                (new ImageAuthor())
                    ->setUser( $articleAuthor->getUser() )
                    ->setCreatedAt( $articleAuthor->getCreatedAt() )
                    ->setUpdatedAt( $articleAuthor->getCreatedAt() )
                    ->setRanking($idx + 1);

            $entityTli2Image->addAuthor($imageAuthor);
        }

        $this->em->persist($entityTli2Image);
        $this->arrNewImages[$imageId] = $entityTli2Image;

        // file copy
        $imageService   = $this->imageFactory->create($entityTli2Image);
        $filename       = $imageService->getOriginalFileName();
        $sourceFilePath = $this->projectDir->getVarDirFromFilePath("uploaded-assets-downloaded-from-remote/images/$filename");
        $destFilePath   = $imageService->getOriginalFilePath();

        if( !file_exists($sourceFilePath) ) {
            return $this->fxWarning("The related image file is missing: " . print_r($arrImage, true) );
        }

        $copyResult = copy($sourceFilePath, $destFilePath);
        if( $copyResult !== true ) {
            return $this->endWithError("Failed to copy image file ##$sourceFilePath## to ##$destFilePath##");
        }
    }


    protected function assignSpotlight(int $articleId, ArticleEntity $article) : static
    {
        $spotlightId   = $this->arrSpotlightIds[$articleId] ?? null;
        $spotlight     = $this->arrNewImages[$spotlightId] ?? null;

        $article->setSpotlight($spotlight);
        return $this;
    }


    protected function importTags() : static
    {
        if( $this->getCliOption(static::OPT_SKIP_TAGS) ) {

            $this->arrNewTags = $this->em->getRepository(TagEntity::class)->loadAll();
            return $this->fxWarning('🦘 Skipped!');
        }

        $this->io->text("Loading TLI1 tags...");
        $stmt = $this->dbTli1->query("
            SELECT
                tag.*, COUNT(1) AS usageCount
            FROM
                tag
            LEFT JOIN
                etichette
            ON
                tag.id_tag = etichette.id_tag
            WHERE
                etichette.tipo = 'contenuto'
            GROUP BY
                etichette.id_tag
            ORDER BY
                tag.id_tag ASC
        ");
        $arrTli1Tags = $stmt->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
        $this->fxOK( count($arrTli1Tags) . " items loaded");

        $this->io->text("Loading TLI2 tags...");
        $arrTli2Tags = $this->em->getRepository(TagEntity::class)->loadAll();
        $this->fxOK( count($arrTli2Tags) . " item(s) loaded");
        unset($arrTli2Tags);

        $this->io->text("Processing every TLI2 tag...");
        $this->processItems($arrTli1Tags, [$this, 'processTli1Tag'], null, [$this, 'buildItemTitle']);

        return $this;
    }


    protected function processTli1Tag(int $tagId, array $arrTag)
    {
        $title      = $arrTag["tag"];
        $ranking    = (int)$arrTag["peso"];
        $createdAt = \DateTime::createFromFormat('YmdHis', $arrTag["data_creazione"]);

        /** @var TagEntity $entityTli2Tag */
        $entityTli2Tag =
            $this->em->getRepository(TagEntity::class)
                ->selectOrNew($tagId)
                ->setTitle($title)
                ->setRanking($ranking)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($createdAt);

        // AUTHORS
        $arrTli1Authors = $this->arrAuthorsByContributionType["tag"][$tagId] ?? [];
        if( empty($arrTli1Authors) ) {
            $this->fxWarning("This Tag has no Authors: " . print_r($arrTag, true) );
        }

        foreach($arrTli1Authors as $idx => $arrOldAuthorData) {

            $author =
                (new TagAuthor())
                    ->setUser( $arrOldAuthorData["user"] )
                    ->setCreatedAt( $arrOldAuthorData["date"] )
                    ->setUpdatedAt( $arrOldAuthorData["date"] )
                    ->setRanking( $idx + 1 );

            $entityTli2Tag->addAuthor($author);
        }

        $this->em->persist($entityTli2Tag);
        $this->arrNewTags[$tagId] = $entityTli2Tag;
    }


    protected function processInvalidTli1TagAssoc() : static
    {
        if( $this->getCliOption(static::OPT_SKIP_TAGS) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

        $this->io->text("Removing associations to non-existing tags...");
        $this->dbTli1->exec("
            DELETE etichette FROM etichette
            LEFT JOIN tag
            ON etichette.id_tag = tag.id_tag
            WHERE tag.id_tag IS NULL
        ");

        return $this->fxOK();
    }


    public function tagArticles() : static
    {
        if( $this->getCliOption(static::OPT_SKIP_TAGS) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

        $this->io->text("Loading TLI1 tag associations...");
        $stmt = $this->dbTli1->query("SELECT * FROM etichette WHERE tipo = 'contenuto' ORDER BY data_creazione ASC");
        $arrTli1TagAssoc = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->fxOK( count($arrTli1TagAssoc) . " items loaded");

        $this->io->text("Processing every TLI1 tag association...");
        $this->processItems($arrTli1TagAssoc, [$this, 'processTli1TagAssoc'], null, [$this, 'buildItemTitle']);

        return $this;
    }


    protected function processTli1TagAssoc(int $none, array $arrTagAssoc)
    {
        $articleId  = $arrTagAssoc["id_opera"];
        /** @var ArticleEntity $article */
        $article    = $this->arrNewArticles[$articleId] ?? null;

        if ( empty($article) ) {
            return $this->endWithError("No related article: " . print_r($arrTagAssoc, true) );
        }

        $tagId  = $arrTagAssoc["id_tag"];
        $tag    = $this->arrNewTags[$tagId] ?? null;

        if ( empty($tag) ) {
            return $this->endWithError("No related tag: " . print_r($arrTagAssoc, true) );
        }

        $attacherId = $arrTagAssoc["id_utente"];
        $attacher   = $this->repoUsers->selectOrNull($attacherId) ?? $article->getAuthors()->first()->getUser();
        if( empty($attacher) ) {
            $this->fxWarning(
                "This Tag Assoc has no Authors: " . print_r([
                    "article_id"    => $article->getId(),
                    "tag_id"        => $tag->getId()
                ], true)
            );
        }

        $createdAt  = \DateTime::createFromFormat('YmdHis', $arrTagAssoc["data_creazione"]);

        $articleTag =
            (new ArticleTag())
                ->setTag($tag)
                ->setUser($attacher)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($createdAt);

        $article->addTag($articleTag);
    }


    protected function importFiles() : static
    {
        if( $this->getCliOption(static::OPT_SKIP_FILES) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

        $this->io->text("Loading TLI1 files...");
        $stmt = $this->dbTli1->query("
            SELECT
                file.*, COUNT(1) AS usageCount
            FROM
                file
            LEFT JOIN
                allegati
            ON
                file.id_file = allegati.id_file
            WHERE
                allegati.tipo = 'contenuto'
            GROUP BY
                allegati.id_file
            ORDER BY
                file.id_file ASC
        ");
        $arrTli1Files = $stmt->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
        $this->fxOK( count($arrTli1Files) . " items loaded");

        $this->io->text("Loading TLI2 files...");
        $arrTli2Files = $this->em->getRepository(FileEntity::class)->loadAll();
        $this->fxOK( count($arrTli2Files) . " item(s) loaded");
        unset($arrTli2Files);

        $this->io->text("Processing every TLI2 file...");
        $this->processItems($arrTli1Files, [$this, 'processTli1File'], null, [$this, 'buildItemTitle']);

        return $this;
    }


    protected function processTli1File(int $fileId, array $arrFile)
    {
        $title      = $arrFile["titolo"];
        $views      = (int)$arrFile["visite"];
        $url        = $arrFile["url"] ?: null;
        $format     = $arrFile["formato"] ?: null;
        $createdAt = \DateTime::createFromFormat('YmdHis', $arrFile["data_creazione"]);

        if( empty($createdAt) ) {
            return $this->endWithError("This File has no date: " . print_r($arrFile, true) );
        }

        /** @var FileEntity $entityTli2File */
        $entityTli2File =
            $this->em->getRepository(FileEntity::class)
                ->selectOrNew($fileId)
                ->setTitle($title)
                ->setViews($views)
                ->setUrl($url)
                ->setFormat($format)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($createdAt);

        // AUTHORS
        $arrTli1Authors = $this->arrAuthorsByContributionType["file"][$fileId] ?? null;

        // we don't have the author for multiple Files - assigning them to "User 2"
        if( empty($arrTli1Authors) ) {
            $arrTli1Authors = [[
                "user"  => $this->repoUsers->selectOrNull(2),
                "date"  => $entityTli2File->getCreatedAt()
            ]];
        }

        foreach($arrTli1Authors as $idx => $arrOldAuthorData) {

            $author =
                (new FileAuthor())
                    ->setUser( $arrOldAuthorData["user"] )
                    ->setCreatedAt( $arrOldAuthorData["date"] )
                    ->setUpdatedAt( $arrOldAuthorData["date"] )
                    ->setRanking( $idx + 1 );

            $entityTli2File->addAuthor($author);
        }

        $this->em->persist($entityTli2File);
        $this->arrNewFiles[$fileId] = $entityTli2File;
    }


    protected function processInvalidTli1FileAssoc() : static
    {
        if( $this->getCliOption(static::OPT_SKIP_FILES) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

        $this->io->text("Removing associations to non-existing files...");
        $this->dbTli1->exec("
            DELETE allegati FROM allegati
            LEFT JOIN file
            ON allegati.id_file = file.id_file
            WHERE file.id_file IS NULL
        ");

        return $this->fxOK();
    }


    public function linkFilesAndArticles() : static
    {
        if( $this->getCliOption(static::OPT_SKIP_FILES) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

        $this->io->text("Loading TLI1 file associations...");
        $stmt = $this->dbTli1->query("SELECT * FROM allegati WHERE tipo = 'contenuto' ORDER BY data ASC");
        $arrTli1FileAssoc = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->fxOK( count($arrTli1FileAssoc) . " items loaded");

        $this->io->text("Processing every TLI1 file association...");
        $this->processItems($arrTli1FileAssoc, [$this, 'processTli1FileAssoc'], null, [$this, 'buildItemTitle']);

        return $this;
    }


    protected function processTli1FileAssoc(int $none, array $arrFileAssoc)
    {
        $articleId  = $arrFileAssoc["id_opera"];
        $article    = $this->arrNewArticles[$articleId] ?? null;
        if ( empty($article) ) {
            return $this->endWithError("No related article: " . print_r($arrFileAssoc, true) );
        }

        $fileId  = $arrFileAssoc["id_file"];
        $file    = $this->arrNewFiles[$fileId] ?? null;
        if ( empty($file) ) {
            return $this->endWithError("No related file: " . print_r($arrFileAssoc, true) );
        }

        $createdAt = \DateTime::createFromFormat('YmdHis', $arrFileAssoc["data"]) ?: $file->getCreatedAt();
        if ( empty($createdAt) ) {
            return $this->endWithError("Invalid attach file date: " . print_r($arrFileAssoc, true) );
        }

        // we didn't track who attached the file to the article on TLI1 => falling back to the first author of the file
        $attacher = $article->getAuthors()->first()->getUser();

        $articleFile =
            (new ArticleFile())
                ->setFile($file)
                ->setUser($attacher)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($createdAt);

        $article->addFile($articleFile);
    }


    protected function importBadges() : static
    {
        if( $this->getCliOption(static::OPT_SKIP_BADGES) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

        $this->io->text("Loading TLI1 badges...");
        $stmt = $this->dbTli1->query("SELECT * FROM bollini");
        $arrTli1Badges = $stmt->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
        $this->fxOK( count($arrTli1Badges) . " items loaded");

        $this->io->text("Loading TLI2 badges...");
        $arrTli2Badges = $this->em->getRepository(BadgeEntity::class)->loadAll();
        $this->fxOK( count($arrTli2Badges) . " item(s) loaded");
        unset($arrTli2Articles);

        $this->io->text("Processing every TLI1 badge...");
        $this->processItems($arrTli1Badges, [$this, 'processTli1Badge'], null, [$this, 'buildItemTitle']);

        return $this;
    }


    protected function processTli1Badge(int $badgeId, array $arrBadge)
    {
        // we don't have any dates for the badge itself => using static value, updating later with the date from the badge assoc
        $createdAt = \DateTime::createFromFormat('Y-m-d H:i:s', '2014-03-13 13:12:13');

        /** @var BadgeEntity $entityTli2Badge */
        $entityTli2Badge =
            $this->em->getRepository(BadgeEntity::class)
                ->selectOrNew($badgeId)
                ->setTitle( $arrBadge["titolo"] )
                ->setAbstract( $arrBadge["testo_breve"] )
                ->setBody( $arrBadge["testo_esteso"] )
                ->setImageUrl( $arrBadge["spotlight"] )
                ->setUserSelectable(false)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($createdAt);

        $this->em->persist($entityTli2Badge);
        $this->arrNewBadges[$badgeId] = $entityTli2Badge;

        return $this;
    }


    public function tagBadges() : static
    {
        if( $this->getCliOption(static::OPT_SKIP_BADGES) ) {
            return $this->fxWarning('🦘 Skipped!');
        }

        $this->io->text("Loading TLI1 tag-badge associations...");
        $stmt = $this->dbTli1->query("SELECT * FROM bollini_assegnati WHERE tipo = 'tag' ORDER BY data_creazione ASC");
        $arrTli1TagAssoc = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->fxOK( count($arrTli1TagAssoc) . " items loaded");

        $this->io->text("Processing every TLI1 tag-badge association...");
        $this->processItems($arrTli1TagAssoc, [$this, 'processTli1TagBadgeAssoc'], null, [$this, 'buildItemTitle']);

        return $this;
    }


    protected function processTli1TagBadgeAssoc(int $none, array $arrTagAssoc)
    {
        $badgeId  = $arrTagAssoc["id_bollino"];
        /** @var BadgeEntity $badge */
        $badge = $this->arrNewBadges[$badgeId] ?? null;

        if ( empty($badge) ) {
            return $this->endWithError("No related badge: " . print_r($arrTagAssoc, true) );
        }

        $tagId  = $arrTagAssoc["id_opera"];
        $tag    = $this->arrNewTags[$tagId] ?? null;
        if ( empty($tag) ) {
            return $this->endWithError("No related tag: " . print_r($arrTagAssoc, true) );
        }

        $createdAt = \DateTime::createFromFormat('YmdHis', $arrTagAssoc["data_creazione"]);
        if ( empty($createdAt) ) {
            return $this->endWithError("No date on this badge assoc: " . print_r($arrTagAssoc, true) );
        }

        // we didn't have the createdAt for the badge => using this one
        $badge
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($createdAt);

        $badgeTag =
            (new TagBadge())
                ->setTag($tag)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($createdAt);

        $badge->addTag($badgeTag);

        $this->em->persist($badge);

        return $this;
    }
}
