<?php
namespace App\Service;


class Dictionary
{
    // https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md
    const array HTML_SPECIAL_CHARS = ['&', '<', '>', '"', "'"];

    const array HTML_QUOTES = [
      '&quote;' => '"',
      '&#39;'   => "'",
      '&apos;'  => "'",
    ];

    // https://www.w3.org/wiki/Common_HTML_entities_used_for_typography
    const array FINE_TYPOGRAPHY_CHARS = [
        // non-breaking space
        "\xc2\xa0" => ' ',
        // dash
        '–' => '-', '—' => '-',
        // Single quotes
        '‘' => "'", '’' => "'",
        // Double quotes
        '“' => '"', '”' => '"',
    ];
}
