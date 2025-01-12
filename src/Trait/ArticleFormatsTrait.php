<?php
namespace App\Trait;

use App\Exception\InvalidEnumException;


trait ArticleFormatsTrait
{
    const int FORMAT_ARTICLE    = 1;
    const int FORMAT_NEWS       = 2;

    public static function getFormats() : array { return [static::FORMAT_ARTICLE, static::FORMAT_NEWS]; }

    public static function validateFormat(int $format) : void
    {
        if( !in_array($format, self::getFormats()) ) {
            throw new InvalidEnumException("Invalid format for the article");
        }
    }
}
