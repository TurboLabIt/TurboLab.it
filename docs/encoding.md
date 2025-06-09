# [Encoding dei caratteri](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md)

L'applicazione deve utilizzare **UTF-8** e **HTML5** ovunque sia possibile.

Vogliamo evitare come la peste l'encoding superfluo delle entity, come `&egrave;` per `è` oppure `&eacute;` per `é`. Al loro posto, utilizzeremo i caratteri "reali", esattamente come vengono visualizzati.


## Caratteri da convertire in entity

Gli unici caratteri da trasformare in entity, **quando figurano nel testo e devono essere mostrati esattamente così come digitati**, sono quelli con un significato speciale in HTML:

- `<` ➡ `&lt;`
- `>` ➡ `&gt;`
- `&` ➡ `&amp;`
- `"` ➡ `&quot;`
- `'` ➡ `&#039;` (nota: `&apos;` crea problemi nei JSON)

Questo è il set minimo: se non vengono trasformati in entity, il parser non è poi in grado di capire se costituiscano istruzioni di markup oppure testo. Ad esempio:

- `<a href>` oppure `1 < 3 > 2`
- `il simbolo di minore in HTML si scrive &lt;` oppure `Barnes & Noble`

*Single-quote* e *double-quote* vanno invece trasformate per poterle inserire negli attributi. Ad esempio: `<img src="..." title="The sign says &quot;Hot stuff&quot;">`.

Rispettando queste specifiche, l'HTML generato supera la [validazione W3C](https://validator.w3.org)


## Quando convertire

La regola da seguire è:

1. **L'ENCODING DELLE ENTITY VA GESTITO IN INSERIMENTO, PRIMA DI SALVARE IL TESTO A DATABASE**
2. **IN VISUALIZZAZIONE, IL TESTO VA MOSTRATO ESATTAMENTE COSI' COME SI TROVA A DATABASE (`|raw`)**

🥷 Seguire queste due regole è di importanza CRITICA. Se si inserisce il testo senza encoding a database, oppure si effettua il decoding in visualizzazione, si rischia XSS.


### Titolo nuovo articolo

Quando l'utente esegue il submit del form da [/scrivi](https://turbolab.it/scrivi), il titolo del nuovo articolo viene POSTato esattamente così come digitato dall'utente (il browser non fa alcuna conversione):

````
Come mostrare un messaggio con JS: <script>alert("bòòm");</script>
````

Prima di salvare questo titolo, basta convertire selettivamente le sole entity citate sopra (`<` ➡ `&lt;` ecc):

````
Come mostrare un messaggio con JS: &lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;
````

In visualizzazione, come detto, mostreremo il testo salvato tal quale.


# Modifica articolo TO BE UPDATED


------



In inserimento, rispettiamo quanto inviato dal browser. Esempio:

````
<p>Per mostrare un messaggio in JS, si usa: <tt>&lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;</tt></p>
<p><img src="..."></p>
<p>Però, testo anche un XSS: <script>alert("bòòm");</script></p>
````

Il sistema deve:

1. recepire il testo
2. trattare le entity come semplice testo (senza modifiche)
3. `<p>`, `<tt>`, ... sono in *whitelist* ed entrano as-is, tutto il resto (incluso `<script>`) va rimosso




## 💣 Caso speciale: importazione da TLI1

**Il corpo** degli articoli importati da TLI1 utilizza *single-quote* e *double-quote* letterali sia per indicare gli attributi (`<img src="..">`) sia per il testo (`il sistema dell'anno 2001 fu chiamato "Windows XP"`).

Per essere conformi alle specifiche indicate sopra, dovrebbe invece essere `<img src=".."> il sistema dell&#039;anno 2001 fu chiamato &quot;Windows XP&quot;`.

Questa ambiguità impedisce di correggere il problema in fase di importazione, ma non crea problemi: l'HTML rimane infatti valido **fino a quando non lo si utilizza in un attributo**, situazione che al momento non si presenta.

Il difetto è presente anche nel titolo e nell'*abstract* degli articoli di TLI1. Ma, poiché questi campi non contengono HTML, possiamo tranquillamente auto-convertire *single-quote* e *double-quote* nelle relative entity durante l'importazione.


## Conversione e archiviazione

La conversione in entity deve obbligatoriamente essere svolta **sul frontend**, prima che il testo raggiunga il backend. In caso contrario, il backend non è in grado di distinguere fra un carattere utilizzato come markup (da lasciare inalterato) e uno da mostrare tale e quale (che deve quindi essere convertito in entity).

Il meglio che possiamo fare sul backend è ripristinare tutte le entity *non-markup* (come `&egrave;` oppure `&eacute;`) riportandole ai caratteri originali. Allo scopo, usiamo [HtmlProcessor::convertEntitiesToUtf8Chars](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/HtmlProcessor.php). Questo avviene:

- durante l'importazione (alcuni vecchi articoli usano `&egrave;` o le "virgolette di Word" `&rsquo;`/`&lsquo;`)
- prima del salvataggio da editor - per sicurezza e consistenza

Il backend deve poi **salvare il dato esattamente come ricevuto**.

Nessuna trasformazione deve poi avvenire nemmeno in fase di estrazione del dato.


## Esempio

🔗 [esempio live su TLI](https://turbolab.it/1939)

Stringa inserita da autore e visualizzata tale e quale dall'utente:

`@ & òàùèéì # § |!"£$%&/()=?^ < > "double-quoted" 'single quoted' \ / | » fine`

HTML utilizzato dal browser:

`<p>@ &amp; òàùèéì # § |!&quot;£$%&amp;/()=?^ &lt; &gt; &quot;double-quoted&quot; &#039;single quoted&#039; \ / | » fine</p>`

Lo stesso HTML è salvato a database.
