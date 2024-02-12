<?php
namespace App\Service;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use TurboLabIt\BaseCommand\Service\ProjectDir;


class StopWords
{
    const FILENAME = 'stopwords-it';

    protected static array $arrStopWords = [];


    public function __construct(protected ProjectDir $projectDir)
    { }


    public function removeFromSting(string $text) : string
    {
        $this->deleteStaleCacheFiles();

        //
        $processedStringCacheAdapter = $this->getCacheAdapter( $this->getProcessedStringCacheFilePath() );
        $processedStringCacheItem = $processedStringCacheAdapter->getItem($text);
        $cachedValue = $processedStringCacheItem->get();
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

        // remove double spaces
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

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

        $processedStringCacheFilePath = $this->getProcessedStringCacheFilePath();
        $processedStringCacheFileModTime = file_exists($processedStringCacheFilePath) ? filemtime($processedStringCacheFilePath) : 0;

        if( $wordsCacheFileModTime > 0 && $sourceFileModTime >= $wordsCacheFileModTime ) {
            unlink($wordsCacheFilePath);
        }

        if( $processedStringCacheFileModTime > 0 && $sourceFileModTime >= $processedStringCacheFileModTime ) {
            unlink($processedStringCacheFilePath);
        }
    }


    protected function getSourceFilePath() : string
    {
        $sourceFilePath = $this->projectDir->getProjectDir(['assets', 'dictionaries']) . static::FILENAME . ".txt";
        return $sourceFilePath;
    }


    protected function getWordsCacheFilePath() : string
    {
        $cacheFilePath = $this->projectDir->getVarDir(['cache']) . static::FILENAME . '_map.cache';
        return $cacheFilePath;
    }


    protected function getProcessedStringCacheFilePath() : string
    {
        $cacheFilePath = $this->projectDir->getVarDir(['cache']) . static::FILENAME . '_processed.cache';
        return $cacheFilePath;
    }


    public function getCacheAdapter(string $cacheFilePath) : PhpArrayAdapter
    {
        // 📚 https://symfony.com/doc/current/components/cache/adapters/php_array_cache_adapter.html
        $cacheAdapter = new PhpArrayAdapter($cacheFilePath, new FilesystemAdapter());
        return $cacheAdapter;
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
        static::$arrStopWords = explode(PHP_EOL, $fileContent);
        foreach(static::$arrStopWords as $key => $value) {

            $value = trim($value);

            if( empty($value) || mb_substr($value, 0, 2) == '##' ) {

                unset(static::$arrStopWords[$key]);
                continue;
            }

            static::$arrStopWords[$key] = $value;
        }

        $this->getCacheAdapter($cacheFilePath)->warmUp([static::FILENAME => static::$arrStopWords]);
    }



}
