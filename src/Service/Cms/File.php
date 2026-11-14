<?php
namespace App\Service\Cms;

use App\Entity\Cms\File as FileEntity;
use App\Exception\FileLogicException;
use App\Exception\FileNotFoundException;
use App\Repository\Cms\FileRepository;
use App\Service\Factory;
use App\Service\HtmlProcessorBase;
use App\Trait\AuthorableTrait;
use App\Trait\VisitableServiceTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;


class File extends BaseCmsService
{
    const string ENTITY_CLASS           = FileEntity::class;
    const string TLI_CLASS              = FileEntity::TLI_CLASS;
    const string NOT_FOUND_EXCEPTION    = FileNotFoundException::class;

    // 👀 https://turbolab.it/scarica/18
    const int ID_LOGO = 18;

    const string UPLOADED_FILES_FOLDER_NAME = parent::UPLOADED_ASSET_FOLDER_NAME . "/files";

    // query parameters that only track the visit: two URLs differing just by these point to the same item
    const string URL_TRACKING_PARAMS_REGEX = '/^(utm_.+|fbclid|gclid|gclsrc|dclid|gbraid|wbraid|msclkid|mc_cid|mc_eid|igshid|yclid|_ga|_gl)$/i';

    const string ATTACHED_BUT_UNUSED_FILE_NAME  = 'files-orphans.json';
    const string MISSING_ON_FILESYSTEM_FILE_NAME= 'files-missing-on-filesystem.json';
    const string MISSING_ON_DATABASE_FILE_NAME  = 'files-missing-on-database.json';

    use AuthorableTrait, VisitableServiceTrait;

    protected ProjectDir $projectDir;
    protected FileEntity $entity;
    protected ?array $arrArticles = null;


    public function __construct(protected Factory $factory)
    {
        $this->clear();
        $this->projectDir = $factory->getProjectDir();
    }

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : FileRepository
    {
        /** @var FileRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(FileEntity::class);
        return $repository;
    }

    public function setEntity(?FileEntity $entity = null) : static
    {
        $this->localViewCount   = $entity?->getViews() ?? 0;
        $this->entity           = $entity ?? new (static::ENTITY_CLASS)();

        return $this;
    }

    public function getEntity() : ?FileEntity { return $this->entity ?? null; }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 👮🏻 Special access ***">
    public function isEditable() : bool { return $this->factory->createFileSentinel($this)->canEdit(); }

    public function enforceCanEdit() : static
    {
        $this->factory->createFileSentinel($this)->enforceCanEdit();
        return $this;
    }


    public function isReadable() : bool { return $this->factory->createFileSentinel($this)->canView(); }

    public function enforceCanView() : static
    {
        $this->factory->createFileSentinel($this)->enforceCanView();
        return $this;
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 👽 Local or remote hosted ***">
    public function getExternalDownloadUrl() : ?string { return $this->entity->getUrl(); }

    public function isLocal() : bool { return empty( $this->getExternalDownloadUrl() ); }

    public function isExternal() : bool { return !$this->isLocal(); }


    /**
     * What an external Download may point to: a web address, a magnet link (BitTorrent) or an ed2k link (eMule).
     * filter_var() alone rejects the last two: they have no host
     */
    public static function isValidExternalUrl(string $url) : bool
    {
        $url = trim($url);

        if( preg_match('/\s/', $url) === 1 ) {
            return false;
        }

        if( preg_match('/^https?:\/\//i', $url) === 1 ) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        return
            preg_match('/^magnet:\?(.+&)?xt=urn:[a-z0-9]+:[^&]+/i', $url) === 1 ||
            preg_match('/^ed2k:\/\/\|[a-z]+\|.+\|\/?$/i', $url) === 1;
    }


    /**
     * Light normalization, for matching only (the stored URL is never rewritten): http/https, "www.", a trailing "/",
     * the #fragment and the tracking parameters make no difference. Anything that isn't http(s) is just trimmed
     */
    public static function normalizeUrlForMatching(string $url) : string
    {
        $url    = trim($url);
        $parts  = parse_url($url);

        if( empty($parts['host']) || !in_array( mb_strtolower($parts['scheme'] ?? ''), ['http', 'https']) ) {
            return $url;
        }

        $normalized = preg_replace('/^www\./', '', mb_strtolower($parts['host']));
        $normalized.= empty($parts['port']) ? '' : ':' . $parts['port'];
        $normalized.= rtrim($parts['path'] ?? '', '/');

        $arrQuery = [];
        foreach( explode('&', $parts['query'] ?? '') as $pair ) {

            $name = urldecode( explode('=', $pair, 2)[0] );
            if( $pair !== '' && preg_match(static::URL_TRACKING_PARAMS_REGEX, $name) !== 1 ) {
                $arrQuery[] = $pair;
            }
        }

        return $normalized . ( empty($arrQuery) ? '' : '?' . implode('&', $arrQuery) );
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 🔎 File type ***">
    public function getMimeType() : ?string
    {
        if( !$this->isLocal() ) {
            return null;
        }

        $filePath = $this->getOriginalFilePath();
        return mime_content_type($filePath);
    }


    public function isBitTorrent() : bool
    {
        return
            stripos( (string) $this->getFormat(), 'torrent') !== false ||
            str_starts_with( (string) $this->getExternalDownloadUrl(), 'magnet' );
    }


    public function isEmule() : bool { return str_starts_with( (string) $this->getExternalDownloadUrl(), 'ed2k' ); }


    public function getCompatibilities() : array
    {
        $format = $this->entity->getFormat();
        if( empty($format) ) {
            return [];
        }

        $arrWindowsFormats = ['exe', 'msi', 'bat', 'microsoft store (app store)', 'cmd', 'ps', 'appx'];
        if( in_array($format, $arrWindowsFormats) ) {
            return [[
                "name"  => "Windows",
                "slug"  => "windows",
                "color" => "#74C0FC"
            ]];
        }

        $arrLinuxFormats = ['service', 'sh'];
        if( in_array($format, $arrLinuxFormats) ) {
            return [[
                "name"  => "Linux",
                "slug"  => "linux",
                "color" => "#000000"
            ]];
        }

        $arrAndroidFormats = ['apk', 'google play (app store)', 'f-droid (app store)'];
        if( in_array($format, $arrAndroidFormats) ) {
            return [[
                "name"  => "Android",
                "slug"  => "android",
                "color" => "#3DDC84"
            ]];
        }

        return [];
    }
    //</editor-fold>



    public function getArticles() : array
    {
        if( is_array($this->arrArticles) ) {
            return $this->arrArticles;
        }

        $this->arrArticles = [];

        $fileJunctionEntities = $this->entity->getArticles();
        foreach($fileJunctionEntities as $junctionEntity) {

            $articleEntity  = $junctionEntity->getArticle();
            $articleId      = $articleEntity->getId();
            $this->arrArticles[$articleId] = $this->factory->createArticle($articleEntity);
        }

        return $this->arrArticles;
    }


    // A file has no publishing status of its own: it inherits visibility from the articles it's attached to
    public function isVisitable() : bool
    {
        foreach($this->getArticles() as $article) {

            if( $article->isVisitable() ) {
                return true;
            }
        }

        return false;
    }


    public function getOriginalFileName() : ?string
    {
        if( !$this->isLocal() ) {
            return null;
        }

        $fileId = $this->entity->getId();
        if( empty($fileId) ) {
            throw new FileLogicException("Cannot get the name of a File without ID");
        }

        $format = $this->entity->getFormat();
        if( empty($format) ) {
            throw new FileLogicException("Cannot get the name of a File without format");
        }

        return implode('.', [$fileId, $format]);
    }


    public function getOriginalFilePath() : ?string
    {
        if( !$this->isLocal() ) {
            return null;
        }

        $fileName = $this->getOriginalFileName();
        return $this->projectDir->createVarDirFromFilePath(static::UPLOADED_FILES_FOLDER_NAME . "/$fileName");
    }


    public function getXSendPath() : string
    {
        return
            DIRECTORY_SEPARATOR .
            implode(DIRECTORY_SEPARATOR, [
                static::UPLOADED_ASSET_XSEND_PATH, 'files', $this->getOriginalFileName()
            ]);
    }


    public function getContent() : string
    {
        $filePath   = $this->getOriginalFilePath();
        return file_get_contents($filePath);
    }


    public function getUserPerceivedFileName() : string
    {
        $fileTitle = $this->getTitle();
        if( empty($fileTitle) ) {
            throw new FileLogicException("Cannot get the name of a File without title");
        }

        $format = $this->entity->getFormat();
        if( empty($format) ) {
            throw new FileLogicException("Cannot get the name of a File without format");
        }

        $fileTitle = HtmlProcessorBase::decode($fileTitle);

        // Remove anything which isn't a word, whitespace, number or any of the following caracters -_~,;[]().
        $fileName = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", ' ', $fileTitle);
        $fileName = trim($fileName);
        $fileName = preg_replace('/\s+/', ' ', $fileName);
        $fileName = trim($fileName);

        $fileName .= "." . $format;
        return $fileName;
    }


    public function getUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->factory->getFileUrlGenerator()->generateUrl($this, $urlType);
    }


    public function getHash() : ?string { return $this->entity->getHash(); }

    public function getFormat() : ?string { return $this->entity->getFormat(); }


    public function getSizeLabel() : ?string
    {
        if( $this->isExternal() ) {
            return null;
        }

        $filepath = $this->getOriginalFilePath();

        if( !is_readable($filepath) ) {
            return null;
        }

        $bytes = filesize($filepath);
        $base = log($bytes, 1024);
        $suffixes = ['bytes', 'kB', 'MB', 'GB', 'TB'];

        // Ensure we don't go out of bounds for the array or handle 0 bytes
        $class = $bytes === 0 ? 0 : floor($base);

        return round(pow(1024, $base - $class), 2) . ' ' . $suffixes[$class];
    }
}
