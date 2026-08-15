<?php
namespace App\Service;


class TextProcessor
{
    /**
     * Full words carrying the WRONG accent, and their fixes — applied word-bounded in any case
     * variant (perchè/Perchè/PERCHÈ) by fixAccentSurrogates(). Word boundaries make onestà, lacchè,
     * campò &co unmatchable. Every entry is opted in one by one, no blanket rules: lacchè blocks a
     * general -chè rule, dà ("dare") blocks touching da, and dò/sù are dictionary-tolerated variants
     * so they are deliberately absent.
     */
    const array ACCENT_MISSPELLED_WORDS = [
        // the -ché compounds: the grave is always wrong on them
        'perchè' => 'perché', 'poichè' => 'poiché', 'affinchè' => 'affinché', 'finchè' => 'finché',
        'benchè' => 'benché', 'nonchè' => 'nonché', 'anzichè' => 'anziché', 'sicchè' => 'sicché',
        'giacchè' => 'giacché', 'cosicchè' => 'cosicché', 'dopodichè' => 'dopodiché',
        'purchè' => 'purché', 'alcunchè' => 'alcunché', 'granchè' => 'granché',
        'pressochè' => 'pressoché', 'fuorchè' => 'fuorché', 'checchè' => 'checché', 'macchè' => 'macché',

        // né and sé always take the acute (nè/sè mean nothing)
        'nè' => 'né', 'sè' => 'sé',

        // ...while these want the grave, not the acute
        'caffé' => 'caffè', 'cioé' => 'cioè', 'ahimé' => 'ahimè',

        // í/ú variants (Einaudi-style or typos): house style wants ì/ù — and sú wants no accent at all.
        // Never generalize í/ú: they are legitimate in foreign proper nouns (Rodríguez, Marratxí)
        'piú' => 'più', 'giú' => 'giù', 'cosí' => 'così', 'sú' => 'su',

        // monosyllables that never take an accent
        'quì' => 'qui', 'quí' => 'qui', 'quà' => 'qua',
        'fù' => 'fu', 'fà' => 'fa', 'và' => 'va', 'stò' => 'sto', 'sò' => 'so', 'nò' => 'no', 'stà' => 'sta',

        // editorial rule, not national orthography: "menu" is the house form, accents dropped
        'menù' => 'menu', 'menú' => 'menu',

        // a standalone é is always the verb è mistyped (naming the letter, "la é chiusa", is the
        // accepted edge case); él and every in-word é (perché) are protected by the boundaries
        'é' => 'è',

        // word-final acute on future-tense verbs: per-word list, NEVER a blanket á→à rule
        // (Bogotá, Panamá are legit) — quoted Spanish futures (dará, será) are the accepted residual
        'sará' => 'sarà', 'verrá' => 'verrà', 'avrá' => 'avrà', 'andrá' => 'andrà',
        'fará' => 'farà', 'dará' => 'darà', 'stará' => 'starà', 'potrá' => 'potrà',
        'dovrá' => 'dovrà', 'vorrá' => 'vorrà', 'saprá' => 'saprà', 'terrá' => 'terrà',
    ];

    /**
     * Words with the accent MISSING entirely — only entries that are no word at all without it.
     * Deliberately absent because the bare form is a legitimate word: necessita/capacita (verbs),
     * unita/stabilita (participles), gravita (verb), pero (the tree), cosi (plural of coso),
     * retro (il retro), gia/cio (Gia, CIO acronym), giacche/checche (plurals of giacca/checca),
     * e/la/si/ne (the core-grammar minefield).
     */
    const array ACCENT_MISSING_WORDS = [
        'perche' => 'perché', 'poiche' => 'poiché', 'affinche' => 'affinché', 'finche' => 'finché',
        'benche' => 'benché', 'nonche' => 'nonché', 'anziche' => 'anziché', 'granche' => 'granché',
        'pressoche' => 'pressoché', 'alcunche' => 'alcunché', 'cosicche' => 'cosicché',
        'dopodiche' => 'dopodiché', 'purche' => 'purché', 'fuorche' => 'fuorché',

        'piu' => 'più', 'giu' => 'giù', 'puo' => 'può', 'virtu' => 'virtù',

        'lunedi' => 'lunedì', 'martedi' => 'martedì', 'mercoledi' => 'mercoledì',
        'giovedi' => 'giovedì', 'venerdi' => 'venerdì',

        'citta' => 'città', 'novita' => 'novità', 'attivita' => 'attività', 'qualita' => 'qualità',
        'quantita' => 'quantità', 'velocita' => 'velocità', 'funzionalita' => 'funzionalità',
        'compatibilita' => 'compatibilità', 'possibilita' => 'possibilità', 'verita' => 'verità',
        'liberta' => 'libertà', 'difficolta' => 'difficoltà', 'connettivita' => 'connettività',
        'utilita' => 'utilità', 'pubblicita' => 'pubblicità', 'modalita' => 'modalità',
        'societa' => 'società', 'disponibilita' => 'disponibilità', 'proprieta' => 'proprietà',
        'priorita' => 'priorità', 'luminosita' => 'luminosità', 'affidabilita' => 'affidabilità',
        'umidita' => 'umidità',
    ];

    /**
     * The vowel+apostrophe surrogate habit (PIU' → PIÙ), guarded so a directly-quoted 'word' is
     * never touched. Includes words whose BARE form is legitimate and therefore missing from
     * ACCENT_MISSING_WORDS: with the trailing apostrophe the surrogate reading is unambiguous
     * (il retro vs retro' = retrò, necessita-verb vs necessita' = necessità).
     */
    const array ACCENT_SURROGATE_WORDS = self::ACCENT_MISSING_WORDS + [
        'necessita' => 'necessità', 'capacita' => 'capacità', 'unita' => 'unità',
        'retro' => 'retrò', 'gogo' => 'gogò', 'pero' => 'però', 'cosi' => 'così',
        'gia' => 'già', 'cio' => 'ciò', 'percio' => 'perciò',

        // editorial rule: menu' meant menù, and the house form is menu
        'menu' => 'menu',
    ];

    /**
     * Editorial proper nouns, enforced by enforceHouseCapitalization() — lowercase form => house form.
     * natale is deliberately absent: it is also the adjective "native" (città natale), a semantic
     * homograph no delimiter can tell apart. pasqua's only lowercase use ("come una pasqua") never
     * occurs in tech prose.
     */
    const array HOUSE_CAPITALIZED_WORDS = [
        'internet' => 'Internet', 'pasqua' => 'Pasqua', 'ferragosto' => 'Ferragosto', 'capodanno' => 'Capodanno',
    ];

    protected ?int $spotlightId = null;
    protected ?string $abstract = null;
    protected array $fileIds = [];


    public function __construct(protected HtmlProcessorForStorage $htmlProcessor) {}


    /**
     * Don't invoke this method directly! Use `$articleEditor->setTitle($title)` if possible
     *
     * @see ArticleEditor
     * @see ArticleEditorTest
     */
    public function processRawInputTitleForStorage(string $title) : string
    {
        // convert back as many &entities; as possible into their corresponding chars — BEFORE
        // cleaning: this way the fine-typography normalization sees real chars, and numeric
        // references (&#8211; for –) are caught like every other spelling of the same char
        $processing = HtmlProcessorBase::decode($title);

        // plain text: replacements must be raw chars (&apos;/&quot; must never reach the DB)
        $processing = $this->cleanTextBeforeStorage($processing, false);

        return $this->fixAccentSurrogates($processing);
    }


    /**
     * Don't invoke this method directly! Use `$articleEditor->setBody($body)` if possible
     *
     * @see ArticleEditor
     * @see ArticleEditorTest
     */
    public function processRawInputBodyForStorage(string $body, bool $allowExtendedHtml = false) : string
    {
        $processing = $this->cleanTextBeforeStorage($body);

        $processing = $this->htmlProcessor->convertLegacyEntitiesToUtf8Chars($processing);
        $processing = $this->htmlProcessor->fixFormattingErrors($processing);
        $processing = $this->htmlProcessor->purify($processing, $allowExtendedHtml);

        // No alt-stripping step here: processArticleBody() rebuilds every <img> as a placeholder
        // without an alt (HTMLPurifier only ever emits an empty alt="" on images, and it is
        // discarded on rebuild). Never regex-rewrite the purified HTML to remove alt — that was the
        // root cause of security-audit finding #26 (it deleted across tag boundaries).
        $processing = $this->htmlProcessor->processArticleBody($processing);

        // second pass: HTMLPurifier decodes numeric references (&#8217; for ’) into raw typographic
        // chars the first pass couldn't see. Raw-char replacements now — the HTML is already purified,
        // and &apos;/&quot; must never reach the DB
        $finalHtml  = $this->cleanTextBeforeStorage($processing, false);
        $finalHtml  = $this->fixAccentSurrogates($finalHtml);

        $this->spotlightId  = $this->htmlProcessor->getSpotlightId();
        $this->fileIds      = $this->htmlProcessor->getFileIds();

        // the abstract was extracted mid-chain, before the second cleaning pass: give it the same
        // treatment, or it would keep the fine typography (and the E') the body just lost
        $abstract       = $this->htmlProcessor->getAbstract();
        $this->abstract = empty($abstract) ? $abstract :
            $this->fixAccentSurrogates( $this->cleanTextBeforeStorage($abstract, false) );

        return trim($finalHtml);
    }


    protected function cleanTextBeforeStorage(string $text, bool $entityEncodeReplacements = true) : string
    {
        // Remove null bytes
        $processing = str_replace("\0", "", $text);

        // replace "fine typography" with their corresponding base equivalents
        $processing = $this->htmlProcessor->replaceUndesiredHtmlEntities($processing, $entityEncodeReplacements);

        // no double-spaces
        $processing = $this->removeDoubleChars($processing);

        return trim($processing);
    }


    protected function removeDoubleChars(string $text, string $char = ' ') : string
    {
        return preg_replace("/$char{2,}/", ' ', $text);
    }


    /**
     * Typewriter-era accent surrogates and the classic accent errors, fixed on normalized text
     * (every apostrophe look-alike is already a straight ' by now):
     *
     *  - sentence-initial E' becomes È — ONLY as a standalone word after start/whitespace/tag/«/"/(:
     *    word-final E' would need the right accent per word, and a quoted 'DONE' must not become 'DONÈ.
     *    A standalone E can only carry the grave, so there is no É ambiguity;
     *  - the word po' (truncation of "poco") is rebuilt from any misspelling: pò, pó, even bare po.
     *    Lowercase only — Po capitalized may be the river — and the bare form only after
     *    start/whitespace/tag/«/"/(: never after a dot (gettext .po files) nor inside paths and URLs;
     *  - three curated word lists fix wrong accents (ACCENT_MISSPELLED_WORDS), missing accents
     *    (ACCENT_MISSING_WORDS) and the vowel+apostrophe surrogate habit (ACCENT_SURROGATE_WORDS),
     *    each fix inheriting the case pattern of what the author typed (PERCHE' → PERCHÉ);
     *  - editorial proper nouns get their capital (HOUSE_CAPITALIZED_WORDS: internet → Internet),
     *    but only between strong prose delimiters and never inside <code> — see
     *    enforceHouseCapitalization() for the exact contract.
     */
    protected function fixAccentSurrogates(string $text) : string
    {
        $text = preg_replace('/(^|[>\s«"(])E\'(?=\s)/u', '$1È', $text);

        $text = preg_replace('/\bp[òó]\'?(?!\p{L})/u', "po'", $text);
        $text = preg_replace('/(^|[>\s«"(])po(?![\'\p{L}\p{N}_])/u', "$1po'", $text);

        $text = $this->fixMisspelledWords(
            $text, static::ACCENT_MISSPELLED_WORDS + static::ACCENT_MISSING_WORDS, false
        );

        $text = $this->fixMisspelledWords($text, static::ACCENT_SURROGATE_WORDS, true);

        return $this->enforceHouseCapitalization($text);
    }


    /**
     * Editorial house-capitalization (Internet is a proper noun), applied ONLY when the word is
     * strongly delimited by prose: start/whitespace/tag/«/"/( on the left, whitespace/tag/closing
     * punctuation on the right — the dot counts only when not followed by a letter, so internet.org
     * stays. URL segments (/internet-security/), paths (\internet explorer) and suffixed tokens can
     * never match, and <code> spans are skipped whole: "digita internet" must stay exactly as typed.
     * Only the all-lowercase form is corrected; INTERNET &co are left to the author. The elision
     * (l'internet) is a deliberate miss: a leading apostrophe may be a quote, not an article.
     */
    protected function enforceHouseCapitalization(string $text) : string
    {
        $regEx =
            '/(^|[>\s«"(])(' . implode('|', array_keys(static::HOUSE_CAPITALIZED_WORDS)) . ')' .
            '(?=$|[\s<»)!?;:,"…]|\.(?!\p{L}))/u';

        $arrChunks = preg_split('~(<code\b[^>]*>.*?</code>)~s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach($arrChunks as $i => $chunk) {

            if( str_starts_with($chunk, '<code') ) {
                continue;
            }

            $arrChunks[$i] = preg_replace_callback(
                $regEx,
                fn(array $arrMatches) : string => $arrMatches[1] . static::HOUSE_CAPITALIZED_WORDS[ $arrMatches[2] ],
                $chunk
            );
        }

        return implode('', $arrChunks);
    }


    /**
     * $trailingApostrophe: false matches the listed words as they are; true matches word+' (the
     * accent-surrogate habit), refusing a directly-quoted 'word'. In both modes a following
     * letter/digit/underscore stops the match (code identifiers stay whole), and the fix inherits
     * the case pattern of what the author typed.
     */
    protected function fixMisspelledWords(string $text, array $arrWords, bool $trailingApostrophe) : string
    {
        $alternation = implode('|', array_keys($arrWords));

        $regEx = $trailingApostrophe
            ? '/(?<!\')\b(?:' . $alternation . ')\'(?!\p{L})/iu'
            : '/\b(?:' . $alternation . ')(?![\'\p{L}\p{N}_])/iu';

        return preg_replace_callback($regEx, function(array $arrMatches) use ($arrWords, $trailingApostrophe) : string {

            $typed = $trailingApostrophe ? mb_substr($arrMatches[0], 0, -1) : $arrMatches[0];
            $fixed = $arrWords[ mb_strtolower($typed, 'UTF-8') ];

            return $this->applyCasePattern($typed, $fixed);

        }, $text);
    }


    /** PERCHE' was typed all-caps → PERCHÉ; Perche' capitalized → Perché; otherwise lowercase */
    protected function applyCasePattern(string $typed, string $fixed) : string
    {
        if( mb_strtoupper($typed, 'UTF-8') === $typed ) {
            return mb_strtoupper($fixed, 'UTF-8');
        }

        $firstChar = mb_substr($typed, 0, 1, 'UTF-8');
        if( mb_strtoupper($firstChar, 'UTF-8') === $firstChar ) {
            return mb_strtoupper(mb_substr($fixed, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($fixed, 1, null, 'UTF-8');
        }

        return $fixed;
    }




    public function getSpotlightId() : ?int { return $this->spotlightId; }

    public function getAbstract() : ?string { return $this->abstract; }

    public function getFileIds() : array { return $this->fileIds; }
}
