<?php
namespace App\Service;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use TurboLabIt\BaseCommand\Service\ProjectDir;


class StopWords
{
    const string FILENAME = 'stopwords-it';

    protected static array $arrStopWords = [];


    public function __construct(protected ProjectDir $projectDir) {}


    public function removeFromSting(string $text) : string
    {
        // prepare quotes
        $arrQuotesMap = [
            '“' => '"',
            '”' => '"',
            '‘' => "'",
            '’' => "'",
            '`' => "'",
            '´' => "'"
        ];

        $text = str_ireplace( array_keys($arrQuotesMap), array_values($arrQuotesMap), $text );

        $this->deleteStaleCacheFiles();

        //
        $processedStringCacheFilePath   = $this->getProcessedStringCacheFilePath();
        $processedStringCacheAdapter    = $this->getCacheAdapter($processedStringCacheFilePath);
        $processedStringCacheItem       = $processedStringCacheAdapter->getItem($text);
        $cachedValue                    = $processedStringCacheItem->get();
        if( !empty($cachedValue) ) {
            return $cachedValue;
        }

        //
        $this->loadDictionaryArray();

        //
        $text = trim($text);

        foreach(static::$arrStopWords as $stopword) {

            $regex = '/\b' . $stopword . '\b/iu';
            $textClean = preg_replace($regex, '', $text);
            $text = $textClean;
        }

        $text = trim($text);

        // remove double spaces
        do {
            $previousText = $text;
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

        } while( $text !== $previousText );

        $processedStringCacheFilePath = $this->getProcessedStringCacheFilePath();
        if( !file_exists($processedStringCacheFilePath) ) {
            $this->getCacheAdapter($processedStringCacheFilePath)->warmUp([static::FILENAME => 'init']);
        }

        $processedStringCacheItem->set($text);
        $processedStringCacheAdapter->save($processedStringCacheItem);

        return $text;
    }


    protected function deleteStaleCacheFiles()
    {
        $sourceFilePath = $this->getSourceFilePath();
        $sourceFileModTime = filemtime($sourceFilePath);

        $wordsCacheFilePath = $this->getWordsCacheFilePath();
        $wordsCacheFileModTime = file_exists($wordsCacheFilePath) ? filemtime($wordsCacheFilePath) : 0;

        if( $wordsCacheFileModTime > 0 && $sourceFileModTime >= $wordsCacheFileModTime ) {

            $this->getCacheAdapter($wordsCacheFileModTime)->clear();

            $processedStringCacheFilePath = $this->getProcessedStringCacheFilePath();
            $this->getCacheAdapter($processedStringCacheFilePath)->clear();

            unlink($wordsCacheFilePath);
        }
    }


    protected function getSourceFilePath() : string
    {
        return $this->projectDir->getProjectDir(['assets', 'dictionaries']) . static::FILENAME . ".txt";
    }


    protected function getWordsCacheFilePath() : string
    {
        return $this->projectDir->createVarDirFromFilePath(['cache', static::FILENAME, static::FILENAME . '_map.cache']);
    }


    protected function getProcessedStringCacheFilePath() : string
    {
        return$this->projectDir->createVarDirFromFilePath(['cache', static::FILENAME, static::FILENAME . '_processed.cache']);
    }


    public function getCacheAdapter(string $cacheFilePath) : PhpArrayAdapter
    {
        $symfonyCacheDirPath = $this->projectDir->getVarDir('cache');

        // 📚 https://symfony.com/doc/current/components/cache/adapters/php_array_cache_adapter.html
        return new PhpArrayAdapter($cacheFilePath, new FilesystemAdapter(static::FILENAME, 0, $symfonyCacheDirPath));
    }


    protected function loadDictionaryArray() : void
    {
        if( !empty(static::$arrStopWords) ) {
            return;
        }

        $cacheFilePath = $this->getWordsCacheFilePath();
        if( file_exists($cacheFilePath) ) {

            static::$arrStopWords = $this->getCacheAdapter($cacheFilePath)->getItem(static::FILENAME)->get();
            return;
        }

        //
        $fileContent = file_get_contents( $this->getSourceFilePath() );
        static::$arrStopWords = array_unique( explode(PHP_EOL, $fileContent) );
        foreach(static::$arrStopWords as $key => $value) {

            $value = trim($value);

            if( empty($value) || mb_substr($value, 0, 2) == '##' ) {

                unset(static::$arrStopWords[$key]);
                continue;
            }

            static::$arrStopWords[$key] = $value;
        }

        usort(static::$arrStopWords, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        $this->getCacheAdapter($cacheFilePath)->warmUp([static::FILENAME => static::$arrStopWords]);
    }
}
