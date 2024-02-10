# Encoding dei caratteri

L'applicazione deve utilizzare **UTF-8** e **HTML5** ovunque sia possibile.

Vogliamo evitare come la peste l'encoding superfluo delle entity, come `&egrave;` per `è` oppure `&eacute;` per `é`. Al loro posto, utilizzeremo i caratteri "reali", esattamente come vengono visualizzati.


## Caratteri da convertire in entity

Gli unici caratteri a dover essere trasformati in entity, **quando figurano nel testo e devono essere mostrati esattamente così come digitati**, sono quelli con un significato speciale in HTML:

- `<` ➡ `&lt;`
- `>` ➡ `&gt;`
- `&` ➡ `&amp;`
- `"` ➡ `&quot;`
- `'` ➡ `&apos;`

Questo è il set assolutamente minimo: se non vengono trasformati in entity, il parser non è poi in grado di capire se costituiscono istruzioni di markup oppure testo. Ad esempio:

- `<a>` oppure `1 < 3 > 2`
- `Barnes & Noble` oppure `il simbolo di maggiore: &lt;`

*Single-quote* e *double-quote* vanno invece trasformate per poterle inserire negli attributi. Ad esempio: `<img src="..." title="The sign says &quot;Matt's Stuff&quot;">`.

Rispettando queste specifiche, l'HTML generato supera la [validazione W3C](https://validator.w3.org)


## Conversione e archiviazione

La conversione in entity deve obbligatoriamente essere svolta **sul frontend**, prima che il testo raggiunga il backend. In caso contrario, il backend non è in grado di distinguere fra un carattere utilizzato come markup (da lasciare inalterato) e uno da mostrare tale e quale (che deve quindi essere convertito in entity).

Il meglio che possiamo fare sul backend è ripristinare tutte le entity *non-markup* (come `&egrave;` oppure `&eacute;`) riportandole ai caratteri originali. Allo scopo, usiamo [HtmlProcessor::convertEntitiesToUtf8Chars](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/HtmlProcessor.php). Questo avviene:

- durante l'importazione - alcuni vecchi articoli usano `&egrave;` o le "virgolette di Word" `&rsquo;`/`&lsquo;`
- prima del salvataggio da editor - per sicurezza e consistenza

Il backend deve poi **salvare il dato esattamente come ricevuto**.

Nessuna trasformazione deve poi avvenire nemmeno in fase di estrazione del dato.


## Esempio

🔗 [esempio live su TLI](https://turbolab.it/1939)

Stringa inserita da autore e visualizzata tale e quale dall'utente:

`@ & òàùèéì # § |!"£$%&/()=?^ < > "double-quoted" 'single quoted' \ / | » fine`

HTML utilizzato dal browser:

`<p>@ &amp; òàùèéì # § |!&quot;£$%&amp;/()=?^ &lt; &gt; &quot;double-quoted&quot; &apos;single quoted&apos; \ / | » fine</p>`

Lo stesso HTML è salvato a database.
