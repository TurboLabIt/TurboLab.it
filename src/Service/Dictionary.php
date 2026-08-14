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
        // dash: hyphen, non-breaking hyphen, figure, en, em, horizontal bar, minus sign
        '‐' => '-', '‑' => '-', '‒' => '-', '–' => '-', '—' => '-', '―' => '-', '−' => '-',
        // Single quotes
        '‘' => "'", '’' => "'",
        // apostrophe look-alikes: acute accent, turned comma (ʻokina), modifier apostrophe,
        // low-9 quote, high-reversed-9 quote, prime, fullwidth apostrophe
        '´' => "'", 'ʻ' => "'", 'ʼ' => "'", '‚' => "'", '‛' => "'", '′' => "'", '＇' => "'",
        // Double quotes and look-alikes: low-9, high-reversed-9, double prime, fullwidth.
        // Guillemets « » (and ‹ ›) are deliberately NOT here: the editorial guidelines require them for citations
        '“' => '"', '”' => '"', '„' => '"', '‟' => '"', '″' => '"', '＂' => '"',
    ];
}
