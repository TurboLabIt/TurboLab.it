# [Encoding dei caratteri](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md)

L'applicazione deve utilizzare **UTF-8** e **HTML5** ovunque sia possibile.

🛑 Dobbiamo evitare di **salvare a database** i caratteri trasformati nelle rispettive entity (`&egrave;` per `è` oppure `&eacute;` per `é`) se non è STRETTAMENTE indispensabile 🛑

Gli unici caratteri da trasformare in entity, **quando sono parte del contenuto editoriale e devono dunque essere mostrati esattamente così come digitati**, sono quelli con un significato speciale in HTML:

- `&` ➡ `&amp;`
- `<` ➡ `&lt;`
- `>` ➡ `&gt;`

Questo è il set minimo: se non vengono trasformati in entity, il parser non è poi in grado di capire se costituiscano istruzioni di markup oppure testo. Ad esempio:

- `<strong>` oppure `1 < 3 > 2`

`"` (`&quot;`) e `'` (`&apos;`) devono essere trasformate in entity SOLO quando sono contenuto editoriale e dobbiamo stamparli all'interno di un attributo HTML. Questo, generalmente, avviene solo in visualizzazione: a database queste entity non devono figurare.

Rispettando queste specifiche, l'HTML generato supera la [validazione W3C](https://validator.w3.org).


## Quando convertire

La regola da seguire è:

> the database should store the cleanest possible raw data: sanitize on input, escape on output

L'argomentazione è:

> it gives you the most flexibility when choosing how and where to output that data

Alla luce di questo, utilizziamo strategie differenti a seconda che il testo sia:

1. **testo semplice** che non deve contenere tag HTML - ad esempio: titoli e tag
2. **HTML vero e proprio** - ad esempio: abstract o body degli articoli


## Strategia 1: testo semplice (no-HTML)

> decode, store, encode-on-view

Quando l'utente esegue il submit del form da [/scrivi](https://turbolab.it/scrivi), il titolo del nuovo articolo viene POSTato esattamente così come digitato dall'utente (il browser non fa alcuna conversione):

````
Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
````

La strategia prevede di:

1. decodificare le entity in caratteri - non strettamente necessario in inserimento (lo è in modifica, v. seguito), ma gestisce comunque la circostanza in cui l'autore scriva volontariamente e letteralmente `per&ograve;`
2. salvare il testo a database as-is, inclusi eventuali tag `<script>`
3. in visualizzazione, per il tag `<title>` oppure `<h1>`: trasformare le entity minime (`&`, `&lt;`, `&gt;`)
4. in visualizzazione, per i tag come `<meta ... content="<title>"`: trasformare le entity minime, più `&quot;` e `&apos;`

---

Allo stesso modo: quando l'utente effettua il submit del titolo modificato, il testo viene POSTato esattamente così com'è (il browser non fa alcuna conversione). In questo caso, però, la trasformazione in entity era già stata svolta dall'applicazione. Riceveremo quindi:

````
Come mostrare un "messaggio" con 'JS': &lt;script&gt;alert("bòòm");&lt;/script&gt;
````

Applicando la stessa strategia di cui sopra, il testo verrà comunque salvato correttamente.


## Strategia 2: HTML vero e proprio

> purify, view-as-is

Qui non possiamo applicare la strategia precedente, perché abbiamo un misto di testo semplice e HTML. Solo il client conosce le semantiche "testo" oppure "tag HTML" di quanto POSTato:

````
<p>Per mostrare un "messaggio" in \'JS\', si usa: <tt>&lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;</tt></p>
<p><img src="..."></p>
<p>Però, testo anche un XSS: <script>alert("bòòm");</script></p>
````

Ci limitiamo dunque a:

1. utilizzare [HTML Purifier](http://htmlpurifier.org/) per rimuovere i tag malformati o non permessi, compreso `<script>`
2. salvare il testo ottenuto a database
3. in visualizzazione: mostriamo il testo as-is


## Salvataggio

L'ingresso in salvataggio è sempre [TextProcessor](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/TextProcessor.php), con un metodo dedicato a ogni strategia:

- **Strategia 1** (titoli, tag): `processRawInputTitleForStorage()` — decodifica le entity (`HtmlProcessorBase::decode()`) e salva il testo grezzo
- **Strategia 2** (body dell'articolo): `processRawInputBodyForStorage()` — passa l'HTML in `HTMLPurifier`, dentro [HtmlProcessorForStorage](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/HtmlProcessorForStorage.php)


## Visualizzazione

I metodi PHP devono sempre ritornare il testo così-come-salvato. La gestione del rendering corretto è demandata a Twig:

- **Strategia 1** (titoli, tag): nulla da fare se non invocare il campo (es.: `{{ Article.title }}`)
- **Strategia 2** (body dell'articolo): utilizzare `|raw` per mostrare l'HTML `es.: {{ Article.bodyForDisplay|raw }}`

---

Eccezione: phpBB salva a database i titoli e nomi già encodati:

````
Commenti a &quot;Ricevere &quot;TurboLab.it&quot; via email: Come dis/iscriversi dalla newsletter&quot;
````

I relativi metodi PHP ([Post->getTitle()](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/PhpBB/Post.php), [User->getUsername()](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/User.php)) devono quindi decodificarli esplicitamente prima di ritornare.


## Articolo e test

🔗 Esempio live su TLI: [Come svolgere test automatici su TurboLab.it](https://turbolab.it/1939)

I test su questo articolo vengono eseguiti in:

- [ArticleEditorTest](https://github.com/TurboLabIt/TurboLab.it/blob/main/tests/Editor/ArticleEditorTest.php)
- [ArticleTest](https://github.com/TurboLabIt/TurboLab.it/blob/main/tests/Smoke/ArticleTest.php)


## Riferimenti

- stackoverflow: [Store html entities in database?](https://stackoverflow.com/q/1970880/1204976)
- softwareengineering: [Should I HTML encode all output from my API?](https://softwareengineering.stackexchange.com/q/117512/165409)
- forum TLI: [phpBB come salva l'HTML a database?](https://turbolab.it/forum/viewtopic.php?t=13553)
