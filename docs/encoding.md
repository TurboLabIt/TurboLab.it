# [Encoding dei caratteri](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md)

L'applicazione deve utilizzare **UTF-8** e **HTML5** ovunque sia possibile.

🛑 Dobbiamo evitare di **salvare a database** i caratteri trasformati nelle rispettive entity (`&egrave;` per `è` oppure `&eacute;` per `é`) se non è STRETTAMENTE indispensabile 🛑

Gli unici caratteri da trasformare in entity, **quando sono parte del contenuto editoriale e devono dunque essere mostrati esattamente così come digitati**, sono quelli con un significato speciale in HTML:

- `&` ➡ `&amp;`
- `<` ➡ `&lt;`
- `>` ➡ `&gt;`

Questo è il set minimo: se non vengono trasformati in entity, il parser non è poi in grado di capire se costituiscano istruzioni di markup oppure testo. Ad esempio:

- `<a href>` oppure `1 < 3 > 2`
- `il simbolo di minore in HTML si scrive &lt;` oppure `Barnes & Noble`

`"` (`&quot;`) e `'` (`&apos;`) devono essere trasformate in entity SOLO quando sono contenuto editoriale e dobbiamo stamparli all'interno di un attributo HTML. Questo, generalmente, avviene solo in visualizzazione: a database, generalmente, queste entity non devono figurare.

Rispettando queste specifiche, l'HTML generato supera la [validazione W3C](https://validator.w3.org).


## Quando convertire

La regola da seguire è:

> the database should store the cleanest possible raw data

> sanitize on input, escape on output

L'argomentazione è:

> it gives you the most flexibility when choosing how and where to output that data

Alla luce di questo, utilizziamo strategie differenti a seconda che il testo sia:

1. **testo semplice** che non deve contenere HTML - ad esempio: titoli e tag
2. **HTML vero e proprio** - ad esempio: abstract o body degli articoli


## Strategia 1: testo semplice (no-HTML)

> decode, store, encode-on-view

Quando l'utente esegue il submit del form da [/scrivi](https://turbolab.it/scrivi), il titolo del nuovo articolo viene POSTato esattamente così come digitato dall'utente (il browser non fa alcuna conversione):

````
Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
````

La strategia prevede di:

1. decodificare le entity in caratteri - non strettamente necessario in inserimento (lo è in modifica), ma gestisce comunque la circostanza in cui l'autore scriva volontariamente e letteralmente `per&ograve;`
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

Qui non possiamo applicare la strategia precedente, perché abbiamo un misto di testo semplice e HTML. Solo il client conosce le semantiche "testo" oppure "tag HTML" di quanto POSTato.

Ipotizzando un submit come questo:

````
<p>Per mostrare un "messaggio" in \'JS\', si usa: <tt>&lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;</tt></p>
<p><img src="..."></p>
<p>Però, testo anche un XSS: <script>alert("bòòm");</script></p>
````

Ci limitiamo dunque a:

1. utilizzare [HTML Purifier](http://htmlpurifier.org/) per rimuovere i tag malformati o non permessi, compreso `<script>`
2. salvare il testo ottenuto a database
3. in visualizzazione: mostriamo il testo as-is
