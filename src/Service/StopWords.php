<?php
namespace App\Service;

use TurboLabIt\BaseCommand\Service\ProjectDir;


class StopWords
{
    const string FILENAME = 'stopwords-it';

    // compiled once per process: one alternation regex matching every stopword
    protected static ?string $regex = null;


    public function __construct(protected ProjectDir $projectDir) {}


    public function removeFromSting(string $text) : string
    {
        $text = trim($text);
        // Single pass over one combined `\b(w1|w2|…)\b` regex
        $text = preg_replace($this->getRegex(), '', $text);
        $text = trim($text);

        // collapse the double spaces left where words were removed
        return preg_replace('/ {2,}/', ' ', $text);
    }


    protected function getRegex() : string
    {
        if( static::$regex !== null ) {
            return static::$regex;
        }

        $sourceFilePath = $this->projectDir->getProjectDir(['assets', 'dictionaries']) . static::FILENAME . ".txt";
        $arrStopWords   = array_unique( explode(PHP_EOL, file_get_contents($sourceFilePath)) );

        $arrCleanStopWords = [];
        foreach($arrStopWords as $value) {

            $value = trim($value);

            // skip blank lines and "##" comment lines
            if( empty($value) || mb_substr($value, 0, 2) == '##' ) {
                continue;
            }

            $arrCleanStopWords[] = $value;
        }

        // longest-first, so the alternation prefers the longest match at any position (e.g. "una" over "un")
        usort($arrCleanStopWords, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        return static::$regex = '/\b(' . implode('|', $arrCleanStopWords) . ')\b/iu';
    }
}
