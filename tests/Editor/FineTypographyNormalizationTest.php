<?php
namespace App\Tests\Editor;

use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Editorial policy: the plain hyphen `-`, the straight apostrophe `'` and the straight double quote `"`
 * are the only forms allowed in TLI content, never the "fine typography" variants. Word, Google Docs and
 * macOS all auto-convert while typing, so pasted text is full of them: they are normalized on the way in,
 * in titles and bodies alike, whatever their spelling — raw UTF-8 char, named entity or numeric reference.
 *
 * The full set lives in Dictionary::FINE_TYPOGRAPHY_CHARS. The backtick ` is deliberately NOT in it.
 *
 * The normalized DB value must hold the raw standard char: finding a literal &apos;/&quot; in a stored
 * title or body means replaceUndesiredHtmlEntities() ran in entity-encoding mode where it must not.
 *
 * @see \App\Service\Dictionary::FINE_TYPOGRAPHY_CHARS
 * @see \App\Service\HtmlProcessorBase::replaceUndesiredHtmlEntities()
 * @see \App\Service\TextProcessor::cleanTextBeforeStorage()
 */
class FineTypographyNormalizationTest extends BaseT
{
    public static function fineTypographyProvider() : array
    {
        return [
            // every dash look-alike becomes a plain hyphen
            'hyphen (U+2010), raw'              => ['‐', '-'],
            'non-breaking hyphen (U+2011), raw' => ['‑', '-'],
            'figure dash (U+2012), raw'         => ['‒', '-'],
            'en dash (U+2013), raw'             => ['–', '-'],
            'em dash (U+2014), raw'             => ['—', '-'],
            'horizontal bar (U+2015), raw'      => ['―', '-'],
            'minus sign (U+2212), raw'          => ['−', '-'],
            'hyphen, named entity'              => ['&hyphen;', '-'],
            'hyphen, named entity alias'        => ['&dash;', '-'],
            'en dash, named entity'             => ['&ndash;', '-'],
            'em dash, named entity'             => ['&mdash;', '-'],
            'horizontal bar, named entity'      => ['&horbar;', '-'],
            'minus sign, named entity'          => ['&minus;', '-'],

            // every apostrophe/single-quote look-alike becomes a straight apostrophe
            'left single quote (U+2018), raw'   => ['‘', "'"],
            'right single quote (U+2019), raw'  => ['’', "'"],
            'acute accent (U+00B4), raw'        => ['´', "'"],
            'turned comma (U+02BB), raw'        => ['ʻ', "'"],
            'modifier apostrophe (U+02BC), raw' => ['ʼ', "'"],
            'low-9 quote (U+201A), raw'         => ['‚', "'"],
            'high-reversed-9 (U+201B), raw'     => ['‛', "'"],
            'prime (U+2032), raw'               => ['′', "'"],
            'fullwidth apostrophe (U+FF07), raw'=> ['＇', "'"],
            'left single quote, named entity'   => ['&lsquo;', "'"],
            'right single quote, named entity'  => ['&rsquo;', "'"],
            'acute accent, named entity'        => ['&acute;', "'"],
            'low-9 quote, named entity'         => ['&sbquo;', "'"],
            'low-9 quote, named entity alias'   => ['&lsquor;', "'"],
            'prime, named entity'               => ['&prime;', "'"],

            // every double-quote look-alike becomes a straight double quote
            'left double quote (U+201C), raw'   => ['“', '"'],
            'right double quote (U+201D), raw'  => ['”', '"'],
            'low-9 double quote (U+201E), raw'  => ['„', '"'],
            'high-rev-9 dbl quote (U+201F), raw'=> ['‟', '"'],
            'double prime (U+2033), raw'        => ['″', '"'],
            'fullwidth dbl quote (U+FF02), raw' => ['＂', '"'],
            'left double quote, named entity'   => ['&ldquo;', '"'],
            'right double quote, named entity'  => ['&rdquo;', '"'],
            'low-9 double quote, named entity'  => ['&bdquo;', '"'],
            'double prime, named entity'        => ['&Prime;', '"'],
        ];
    }


    #[DataProvider('fineTypographyProvider')]
    public function testFineTypographyBecomesStandardCharsOnStorage(string $fineForm, string $standardChar) : void
    {
        $probe = bin2hex(random_bytes(4));

        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle("Typo probe $probe la CPU $fineForm quella nuova $fineForm va")
            ->setBody("<p>La CPU $fineForm quella nuova $fineForm va</p>");

        $this->assertSame("Typo probe $probe la CPU $standardChar quella nuova $standardChar va", $editor->getTitle());
        $this->assertSame("<p>La CPU $standardChar quella nuova $standardChar va</p>", $editor->getBody());
    }


    /**
     * Numeric character references (&#8211;, &#x2013;, …) are NOT in the replacement map, yet they must
     * normalize like every other spelling. Each pipeline gets there its own way:
     *
     *  - title: decode() runs FIRST, turning the reference into the raw typographic char the map knows;
     *  - body: cleanTextBeforeStorage() runs a second time at the END of processRawInputBodyForStorage(),
     *    catching the raw chars HTMLPurifier decoded in between.
     *
     * This test pins both orderings: swap decode/clean in the title path, or fold the body's two cleaning
     * passes into one, and it fails.
     */
    public function testNumericEntityFineTypographyIsNormalized() : void
    {
        //         hyphen    nb-hyph   en(hex)    em        minus     acute    r-single  fullwidth l-double  r-double  low-9 dbl dbl-prime
        $input  = 'a &#8208; b &#8209; c &#x2013; d &#8212; e &#8722; f &#180; g &#8217; h &#65287; i &#8220; j &#8221; k &#8222; l &#8243; m';
        $output = "a - b - c - d - e - f ' g ' h ' i \" j \" k \" l \" m";

        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle("Typo probe $input")
            ->setBody("<p>$input</p>");

        $this->assertSame("Typo probe $output", $editor->getTitle());
        $this->assertSame("<p>$output</p>", $editor->getBody());
    }


    /**
     * The non-breaking space becomes a regular space, and never leaves a double space behind:
     * cleanTextBeforeStorage() collapses those right after the replacement.
     */
    public function testNonBreakingSpaceBecomesARegularSpace() : void
    {
        $probe = bin2hex(random_bytes(4));

        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle("Typo probe $probe 5\u{00A0}GHz e 6&nbsp;GHz e 7 \u{00A0} GHz")
            ->setBody("<p>5\u{00A0}GHz e 6&nbsp;GHz e 7 \u{00A0} GHz</p>");

        $this->assertSame("Typo probe $probe 5 GHz e 6 GHz e 7 GHz", $editor->getTitle());
        $this->assertSame('<p>5 GHz e 6 GHz e 7 GHz</p>', $editor->getBody());
    }


    public static function accentSurrogateProvider() : array
    {
        return [
            // sentence-initial E' is the typewriter-era surrogate for È, in every spelling
            "E' at the start"             => ["E' disponibile Firefox", "È disponibile Firefox"],
            "E' after a sentence"         => ["Niente panico. E' una bufala", "Niente panico. È una bufala"],
            "E' after «"                  => ["si dice: «E' finita» ora", "si dice: «È finita» ora"],
            "E' after ("                  => ["(E' importante) leggere", "(È importante) leggere"],
            "E' typed curly"              => ["E’ disponibile Firefox", "È disponibile Firefox"],
            "E' as numeric entity"        => ["E&#8217; disponibile ora", "È disponibile ora"],

            // ...but never word-final (that is the word lists' job), quoted, glued or lowercase
            "FINCHE' fixed by word list"  => ["FINCHE' non si aggiorna", "FINCHÉ non si aggiorna"],
            "quoted 'DONE' stays"         => ["digitare 'DONE' e invio", "digitare 'DONE' e invio"],
            "quoted letter 'E' stays"     => ["premere il tasto 'E' due volte", "premere il tasto 'E' due volte"],
            "E 'sto stays"                => ["E 'sto PC non parte", "E 'sto PC non parte"],
            "lowercase e' stays"          => ["questo e' un test", "questo e' un test"],
            "glued E' stays"              => ["E'possibile fare tutto", "E'possibile fare tutto"],

            // perché always takes the acute — fixed from the wrong grave and from the surrogate
            'perchè'                      => ["perchè non funziona", "perché non funziona"],
            'Perchè'                      => ["Perchè non funziona", "Perché non funziona"],
            'PERCHÈ'                      => ["PERCHÈ NON FUNZIONA", "PERCHÉ NON FUNZIONA"],
            "perche'"                     => ["perche' non funziona", "perché non funziona"],
            "Perche'"                     => ["Perche' non funziona", "Perché non funziona"],
            "PERCHE'"                     => ["PERCHE' NON FUNZIONA", "PERCHÉ NON FUNZIONA"],
            "perche' typed curly"         => ["perche’ non funziona", "perché non funziona"],
            "il perche', noun"            => ["capire il perche' delle cose", "capire il perché delle cose"],
            "perche' before punctuation"  => ["mi chiedo perche', e taccio", "mi chiedo perché, e taccio"],

            // ...but a quoted 'perche' keeps its closing quote, and there is NO blanket -chè rule
            "quoted 'perche' stays"       => ["il 'perche' resta intatto", "il 'perche' resta intatto"],
            'lacchè stays'                => ["il lacchè del re", "il lacchè del re"],

            // the whole -ché family is opted in: wrong grave, missing accent and surrogate alike
            'poichè'                      => ["anche poichè va corretto", "anche poiché va corretto"],
            'affinchè'                    => ["affinchè tutto funzioni", "affinché tutto funzioni"],
            'PURCHÈ all caps'             => ["PURCHÈ SIA GRATIS", "PURCHÉ SIA GRATIS"],
            'benche bare'                 => ["benche sia tardi", "benché sia tardi"],
            "poiche' surrogate"           => ["poiche' serve, si fa", "poiché serve, si fa"],

            // wrong-accent words, any case pattern
            'nè'                          => ["nè carne nè pesce", "né carne né pesce"],
            'Nè capitalized'              => ["Nè uno Nè due", "Né uno Né due"],
            'sè'                          => ["fa tutto da sè", "fa tutto da sé"],
            'caffé'                       => ["pausa caffé al bar", "pausa caffè al bar"],
            'piú acute variant'           => ["sempre di piú oggi", "sempre di più oggi"],
            'sú drops the accent'         => ["pagina che va sú e giú", "pagina che va su e giù"],
            'quì'                         => ["vieni quì subito", "vieni qui subito"],
            'fù'                          => ["fù un errore storico", "fu un errore storico"],
            'stò'                         => ["stò arrivando ora", "sto arrivando ora"],

            // missing accents, only on words that are no word at all without one
            'piu bare'                    => ["non funziona piu, ora", "non funziona più, ora"],
            'puo bare'                    => ["non si puo fare", "non si può fare"],
            'perche bare'                 => ["perche può interessare", "perché può interessare"],
            'citta bare'                  => ["la citta più vicina", "la città più vicina"],
            'velocita bare'               => ["test di velocita adsl", "test di velocità adsl"],
            'lunedi bare'                 => ["torna lunedi prossimo", "torna lunedì prossimo"],
            'CITTA all caps'              => ["LA CITTA DEL FUTURO", "LA CITTÀ DEL FUTURO"],

            // the vowel+apostrophe surrogate habit, case pattern preserved
            "piu' surrogate"              => ["non si aggiorna piu' ora", "non si aggiorna più ora"],
            "PIU' all caps"               => ["NON SI AGGIORNA PIU'", "NON SI AGGIORNA PIÙ"],
            "pubblicita' surrogate"       => ["blocco pubblicita' incluso", "blocco pubblicità incluso"],
            "unita' surrogate"            => ["nascondere unita' USB vuote", "nascondere unità USB vuote"],
            "retro' surrogate"            => ["stile un po' retro' ma bello", "stile un po' retrò ma bello"],
            "pero' surrogate"             => ["pero' funziona bene", "però funziona bene"],
            "gia' surrogate"              => ["l'ho gia' fatto", "l'ho già fatto"],
            "lunedi' surrogate"           => ["ci vediamo lunedi' 4", "ci vediamo lunedì 4"],

            // ...never when the bare word is legitimate: those fix only WITH the apostrophe
            'retro bare stays'            => ["il retro della scheda madre", "il retro della scheda madre"],
            'pero bare stays (the tree)'  => ["il pero del giardino fiorisce", "il pero del giardino fiorisce"],
            'necessita bare stays (verb)' => ["il PC necessita di più RAM", "il PC necessita di più RAM"],
            'capacita bare stays (verb)'  => ["non si capacita del problema", "non si capacita del problema"],
            'giacche bare stays (plural)' => ["le giacche invernali pesanti", "le giacche invernali pesanti"],
            'cosi bare stays'             => ["si presenterà cosi al pubblico", "si presenterà cosi al pubblico"],
            "quoted 'piu' stays"          => ["il 'piu' quotato di tutti", "il 'piu' quotato di tutti"],

            // a standalone é is the verb è mistyped — in-word é and foreign words are untouchable
            'é standalone'                => ["non funziona, é una Beta", "non funziona, è una Beta"],
            'É standalone capital'        => ["É disponibile ora", "È disponibile ora"],
            'é inside a word stays'       => ["il perché delle cose", "il perché delle cose"],
            'él Spanish stays'            => ["disse él con calma", "disse él con calma"],

            // word-final acute on future verbs, per-word list only
            'verrá'                       => ["verrá dichiarato presto", "verrà dichiarato presto"],
            'SARÁ all caps'               => ["SARÁ UN SUCCESSO", "SARÀ UN SUCCESSO"],
            'potrá'                       => ["si potrá fare domani", "si potrà fare domani"],
            'Bogotá stays'                => ["un viaggio a Bogotá oggi", "un viaggio a Bogotá oggi"],

            // editorial rule: menu is the only house form
            'menù'                        => ["apri il menù Start", "apri il menu Start"],
            'Menù capitalized'            => ["Menù di avvio rapido", "Menu di avvio rapido"],
            "menu' surrogate"             => ["dal menu' contestuale", "dal menu contestuale"],
            'menu correct, untouched'     => ["dal menu contestuale", "dal menu contestuale"],
            'Menu at sentence start stays'=> ["Menu di avvio: tre trucchi. Menu per tutti", "Menu di avvio: tre trucchi. Menu per tutti"],

            // po', truncation of "poco", never takes the accent
            'un pò'                       => ["serve un pò di ordine", "serve un po' di ordine"],
            "un pò' double error"         => ["serve un pò' di ordine", "serve un po' di ordine"],
            'pò before punctuation'       => ["aspetta un pò!", "aspetta un po'!"],
            'Pò stays (may be the river)' => ["il fiume Pò straripa", "il fiume Pò straripa"],
            'campò stays'                 => ["l'esercito campò a lungo", "l'esercito campò a lungo"],
            'può stays'                   => ["si può fare", "si può fare"],

            // ...even with no apostrophe at all, but only via the decisive "un po" bigram
            'un po, bare'                 => ["serve un po di ordine", "serve un po' di ordine"],
            'Un po at sentence start'     => ["Un po di pazienza serve", "Un po' di pazienza serve"],
            'un pó, stray acute'          => ["serve un pó di ordine", "serve un po' di ordine"],
            "un po' correct, idempotent"  => ["serve un po' di ordine", "serve un po' di ordine"],
            'UN PO caps stays (acronym)'  => ["gestire UN PO in azienda", "gestire UN PO in azienda"],
            'un polo stays'               => ["comprare un polo USB attivo", "comprare un polo USB attivo"],

            // ...and the bare word po alone is fixed too — but Po (river) and .po (gettext) never are
            'po bare, no un'              => ["metti po di sale qui", "metti po' di sale qui"],
            'il Po river stays'           => ["il Po in piena fa paura", "il Po in piena fa paura"],
            '.po file stays'              => ["aprire il file .po con Poedit", "aprire il file .po con Poedit"],

            // sta ("stare") never takes the accent
            'stà'                         => ["non stà bene", "non sta bene"],
            'Stà'                         => ["Stà attento!", "Sta attento!"],
            'STÀ'                         => ["COSA STÀ FACENDO?", "COSA STA FACENDO?"],
            "aferesi 'stà"                => ["però 'stà cosa funziona", "però 'sta cosa funziona"],
            'onestà stays'                => ["l'onestà paga sempre", "l'onestà paga sempre"],
            'Maestà stays'                => ["Sua Maestà arriva", "Sua Maestà arriva"],

            // the rules compose
            'all together'                => ["E' un pò perchè stà lì", "È un po' perché sta lì"],
        ];
    }


    #[DataProvider('accentSurrogateProvider')]
    public function testAccentSurrogatesAndMisspellingsAreFixedOnStorage(string $input, string $expected) : void
    {
        $probe = bin2hex(random_bytes(4));

        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle("$input $probe")
            ->setBody("<p>$input</p>");

        $this->assertSame("$expected $probe", $editor->getTitle());
        $this->assertSame("<p>$expected</p>", $editor->getBody());
    }


    /**
     * The abstract is extracted from the first paragraph mid-chain, BEFORE the body's second cleaning
     * pass: it must receive the same normalization, or it would keep the fine typography and the E'
     * the body just lost.
     */
    public function testTheExtractedAbstractGetsTheSameNormalization() : void
    {
        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle('Typo probe ' . bin2hex(random_bytes(4)))
            ->setBody("<p>E&#8217; un test – un pò strano, perche’ serve</p>");

        $this->assertSame("È un test - un po' strano, perché serve", $editor->getEntity()->getAbstract());
    }


    /**
     * The editorial guidelines require guillemets « » for citations: they (and their single form ‹ ›)
     * must never join FINE_TYPOGRAPHY_CHARS, or every citation on the site would lose its markers.
     */
    public function testGuillemetsAreLeftAlone() : void
    {
        $probe = bin2hex(random_bytes(4));

        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle("Typo probe $probe ha detto «così sia» e ‹poi› basta")
            ->setBody('<p>Ha detto «così sia» e ‹poi› basta</p>');

        $this->assertSame("Typo probe $probe ha detto «così sia» e ‹poi› basta", $editor->getTitle());
        $this->assertSame('<p>Ha detto «così sia» e ‹poi› basta</p>', $editor->getBody());
    }


    /** The backtick has meaning of its own (inline code in the forum, shell snippets): hands off. */
    public function testTheBacktickIsLeftAlone() : void
    {
        $probe = bin2hex(random_bytes(4));

        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor
            ->setTitle("Typo probe $probe eseguire `ls -la` da terminale")
            ->setBody('<p>Eseguire `ls -la` da terminale</p>');

        $this->assertSame("Typo probe $probe eseguire `ls -la` da terminale", $editor->getTitle());
        $this->assertSame('<p>Eseguire `ls -la` da terminale</p>', $editor->getBody());
    }
}
