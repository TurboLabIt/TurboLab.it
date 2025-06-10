<?php
namespace App\Service;


class Dictionary
{
    const array ACCENTED_LETTERS = ['à', 'á', 'è', 'é', 'ì', 'í', 'ò', 'ó', 'ù', 'ú'];

    // https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md
    const array ENTITIES = ['&', '<', '>', '"', "'"];

    // https://www.w3.org/wiki/Common_HTML_entities_used_for_typography
    const array FINE_TYPOGRAPHY_CHARS = [
        // dash
        '–' => '-', '—' => '-',
        // Single quotes
        '‘' => "'", '’' => "'",
        // Double quotes
        '“' => '"', '”' => '"',
    ];
}
