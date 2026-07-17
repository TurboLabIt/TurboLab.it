# [Audit di accessibilità](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/accessibility-audit.md)

Questo è il documento centralizzato degli audit di accessibilità del sito: raccoglie i problemi individuati nel tempo — sia quelli ⚠️ **ancora aperti**, sia quelli ✅ già risolti — e si aggiorna a ogni nuova analisi. Ogni analisi combina esecuzioni Lighthouse/axe (lab) e revisione del codice sorgente, tipicamente condotte contro la produzione e verificate sull'istanza di sviluppo.

**Ambito**: Sono in scope:

- l'applicazione Symfony (template Twig)
- i CSS in `assets/`

Sono **esclusi** phpBB (`public/forum/`, UI di terze parti) e lo stack pubblicitario (annunci + popup consenso CMP), non modificabili direttamente: i loro problemi vengono comunque registrati (sezione *wontfix*) perché pesano sui punteggi misurati.

**Struttura del documento**: Prima la tabella riassuntiva e le schede dei **problemi aperti**, poi le note su misurazione e punteggi; sotto il separatore, i **rischi accettati (wontfix)** e infine i **problemi risolti**. Le sezioni usano `##`, le singole schede `###`. Quando un problema viene chiuso, la sua scheda si sposta nella sezione finale mantenendo il numero d'ordine originale (identificatore stabile). Le gravità seguono l'*impact* di axe-core (critical/serious ➡ 🟠, moderate ➡ 🟡, minor ➡ ⚪).


## Riepilogo — problemi aperti

| # | Gravità | Titolo | Ambiente |
|---|---------|--------|----------|
| 5 | 🟡 Medio | Gerarchia dei titoli non sequenziale (h1➡h3, h3➡h6, h1➡h4) | prod |


### 5. 🟡 Medio — Gerarchia dei titoli non sequenziale

Audit `heading-order` (peso 3, tutte le pagine). Tre salti:

- home: h1 ➡ h3 sulle card (nessun h2 in pagina) — [home/index.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/home/index.html.twig);
- home: h3 ➡ h6 sui titoli della lista video (`tli-video-thumb-title`);
- articolo: h1 ➡ h4 nel blocco sotto il titolo — [article/index.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/article/index.html.twig).

**Correzione:** ritocco dei tag nei template (h3➡h2, h4➡h2; i titoli-video possono diventare non-heading). Il CSS del tema aggancia le classi (`.title`), non i tag, quindi il cambio dovrebbe essere neutro per lo stile — da verificare a build fatta e su [/1939](https://dev0.turbolab.it/1939).


## Note su misurazione e punteggi

Baseline misurata su **prod** il **2026-07-15** (Lighthouse 13.4 locale, Chrome for Testing 150; home + articolo, mobile + desktop):

| Pagina | Mobile | Desktop |
|--------|--------|---------|
| Home | 89 | 94 |
| Articolo | 85 | 91 |

Fatti che pesano sulla lettura dei numeri:

- **L'accessibilità non è Core Web Vitals.** Il punteggio non entra in CrUX né nel ranking: la motivazione dei fix è WCAG / utenti reali, non la SEO.
- **Gli audit Lighthouse sono binari**: un solo elemento fallito boccia l'intero audit. Il popup consenso Clickio compare in **ogni** run lab a profilo fresco e fallisce `color-contrast` per conto suo ➡ i fix nostri sul contrasto **non muovono il punteggio lab finché il popup è presente**, ma valgono per gli utenti reali (che lo vedono una volta sola) e per i run a consenso memorizzato.
- **Su mobile il tetto è Clickio**: `button-name` (peso 10, il singolo peso più alto) fallisce per il bottone di chiusura dell'annuncio (vedi #6) finché lo stack ads è presente.
- **Le versioni di Lighthouse divergono**: la 13.4 locale non fallisce più `meta-viewport` (finding #8), PSI sì. In caso di discrepanza, indicare sempre con quale versione si è misurato.


### Verifica post-fix 1–4 + 9 (2026-07-15, dev0)

Prima/dopo dei fix contrasto misurati su **dev0** (Lighthouse 13.4 locale, Chrome for Testing 150; home + articolo `/1391`; mediane di 3 run, stack ads attivo). I valori assoluti differiscono dalla baseline prod qui sopra per la web debug toolbar di Symfony, presente solo su dev0:

| Pagina | Accessibilità | Nodi `color-contrast` KO | `link-in-text-block` |
|--------|---------------|--------------------------|----------------------|
| Home mobile | 85 ➡ 85 | 80 ➡ 11 | ok ➡ ok |
| Home desktop | 90 ➡ 90 | 79 ➡ 8 | ok ➡ ok |
| Articolo mobile | 81 ➡ **85** | 25 ➡ 11 | ko ➡ **ok** |
| Articolo desktop | 86 ➡ **90** | 24 ➡ 9 | ko ➡ **ok** |

I nodi `color-contrast` residui sono **tutti** di terze parti o solo-dev (popup Clickio + web debug toolbar): bloccandoli, **l'audit passa con 0 nodi su entrambe le pagine** e l'accessibilità segna **99** — l'unico audit fallito resta `heading-order` (finding #5). Come previsto, il punteggio *lab* della home non si muove (il popup boccia `color-contrast` da solo), mentre l'articolo guadagna +4 dal flip di `link-in-text-block`; su mobile resta il tetto `button-name` (#6). **Performance e Core Web Vitals invariati** (LCP/CLS/TBT entro la varianza run-to-run): atteso, i fix cambiano solo colori. *Revisione 2026-07-16*: la sottolineatura inizialmente usata per il #4 è stata rimossa su valutazione estetica e sostituita dalla distinzione cromatica (vedi scheda #4); punteggi e audit riverificati invariati.


---


## Decisioni: rischi accettati (wontfix)

| # | Gravità | Titolo | Decisione |
|---|---------|--------|-----------|
| 6 | 🟡 Medio | Clickio: bottone di chiusura annuncio senza nome accessibile | wontfix — DOM di terze parti; segnalabile a Clickio |
| 7 | ⚪ Basso | Clickio: contrasti del popup consenso CMP | wontfix — DOM di terze parti; visto una sola volta per utente |


### 6. 🟡 Medio — Clickio: bottone di chiusura annuncio senza nome accessibile 🤝 wontfix (accettato)

L'audit `button-name` (peso 10) fallisce su `#lx_close_button`, il bottone di chiusura dell'annuncio Clickio su mobile: nessun testo né `aria-label`, quindi uno *screen reader* annuncia un bottone anonimo. È il **singolo peso più alto** tra gli audit falliti ed è ciò che impedisce al mobile di superare ~94 anche a fix nostri completati. Il DOM è iniettato dallo script Clickio: non modificabile da noi (patch via JS/CSS nostro = fragile e fuori policy sullo stack ads).

**Ri-aprire se:** Clickio corregge il markup (ri-misurare), o se si apre un ticket presso di loro — unico intervento sensato.


### 7. ⚪ Basso — Clickio: contrasti del popup consenso CMP 🤝 wontfix (accettato)

Il popup CMP (`cl-consent*`) contribuisce con una ventina di elementi sotto soglia (grigi `#838391` su `#f0f4f8`, bottoni `#79accd`) all'audit `color-contrast` di ogni run lab. Markup di terze parti; l'utente reale lo incontra una volta sola. Da tenere presente leggendo i punteggi (vedi note sopra), nessun intervento nostro possibile.


---


## Riepilogo — problemi risolti

| # | Gravità | Titolo | Ambiente |
|---|---------|--------|----------|
| 1 | 🟠 Alto | Contrasto: testo piccolo nel blu brand `#1091FF` ➡ `#0a6cc9` | prod |
| 2 | 🟠 Alto | Contrasto: grigi `rgba(23,34,43,.5)` di metadati, breadcrumbs & co. ➡ `#6c757d` | prod |
| 3 | 🟠 Alto | Contrasto: verde stato di pubblicazione ➡ `#17804f` | prod |
| 4 | 🟠 Alto | Link distinguibili solo dal colore nelle *strip* ➡ contrasto col testo circostante | prod |
| 8 | 🟠 Alto | `maximum-scale=1` bloccava lo zoom ➡ rimosso + `touch-action: manipulation` | prod |
| 9 | 🟠 Alto | Contrasto: rosa Bootstrap dei `<code>` ➡ `#ab296a` | prod |
| 10 | 🟠 Alto | Touch target < 24px nello slideshow centrale della home ➡ padding | prod |
| 11 | 🟡 Medio | Manca il landmark `<main>` ➡ wrap in base.html.twig | prod |

I finding 1–4 nascevano dallo stesso audit Lighthouse `color-contrast`/`link-in-text-block` (2026-07-15): ~160 elementi segnalati. Correzione: un blocco di override in coda ad [app.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/app.css) (nell'entry `app` carica dopo bootstrap e tema ➡ a parità di specificità vince per ordine nella cascata) + correzioni dirette nei CSS/template nostri, senza toccare il tema. Esito misurato: sezione *Verifica post-fix* sopra.


### 1. 🟠 Alto — Contrasto: testo piccolo nel blu brand `#1091FF` ✅ risolto (2026-07-15)

**Problema.** Il blu del tema `#1091FF` rende 3.14:1 su bianco e 2.9:1 su `#f3f3f4` — sotto il minimo WCAG 1.4.3 di **4.5:1** per il testo piccolo. Origine di ~140 dei ~160 elementi segnalati: link categoria e autori nelle card del mosaico (12px), bottone dei commenti (`.post-load-btn a`: bianco su blu a 16px = 3.22:1), voce attiva del menu ([stellarnav.css:17](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/newspark/stellarnav.css)).

**Fix.** **`#0a6cc9`** — stessa tinta, 5.25:1 su bianco, 4.73:1 su `#f3f3f4`, 5.25:1 come sfondo del bottone — nei soli contesti *small-text*: [mosaic-and-slider.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/mosaic-and-slider.css) corretto alla fonte (è CSS nostro e nelle entry `home`/`archive` carica *dopo* app.css: un override non avrebbe vinto la cascata), bottone e voce attiva del menu nel blocco di override di app.css. `#1091FF` **resta** per titoli grandi, hover e sfondi (soglia *large text* 3:1). Rilasciato insieme al #4.


### 2. 🟠 Alto — Contrasto: grigi dei metadati articolo, breadcrumbs & co. ✅ risolto (2026-07-15)

**Problema.** Il grigio `#8B9195` misurato da Lighthouse non è un esadecimale del CSS ma il *computed* di **`rgba(23,34,43,.5)`** su bianco = 3.19:1, usato dal tema in più punti (l'attribuzione originale alle righe 1258/1438 di style.css era errata: quelle sono la footer gallery su fondo scuro, che passa; i breadcrumbs sono regola del tema `.about-author-content nav ol li`, non default Bootstrap). Contesti: barra metadati dell'articolo (`.categories-share ul li`, 15px), breadcrumbs (13px) e — emersi in verifica — etichette PRECEDENTE/SUCCESSIVO (`.post-reader-prev span`) e link "Guarda la discussione sul forum" (`.tli-forum-thread-link a`).

**Fix.** **`#6c757d`** (4.69:1 su bianco) ovunque: override in app.css per le regole del tema, correzione diretta in [article.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/article.css) per il link al topic (CSS nostro). I link della barra metadati (neri `#17222B`) restano distinguibili dal nuovo grigio: 3.44:1 ➡ `link-in-text-block` non scatta.


### 3. 🟠 Alto — Contrasto: verde stato di pubblicazione ✅ risolto (2026-07-15)

**Problema.** Lo *span* "Pubblicato:" usava `#33d17a` inline = **1.99:1** su bianco, il peggior rapporto rilevato — [publishing-status.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/article/publishing-status.html.twig). Il secondo uso (h2 in [editor/new.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/article/editor/new.html.twig)) è testo grande, ma 1.99:1 fallisce anche la soglia *large text* di 3:1.

**Fix.** **`#17804f`** (4.96:1) in entrambi i template.


### 4. 🟠 Alto — Link distinguibili solo dal colore nelle *strip* dei metadati ✅ risolto (2026-07-15)

**Problema.** Audit `link-in-text-block` (peso 7, pagine articolo): nei box "articoli correlati" i link non si distinguono dal testo grigio circostante (2.29:1, minimo 3:1) e nessun blu leggibile può raggiungere 3:1 sia col fondo bianco sia col grigio adiacente: serve un distintivo non-cromatico. Nota emersa in verifica: su quelle pagine i link erano neri (`a` globale del tema, `#17222B`), non blu — `mosaic-and-slider.css` carica solo su home/archivio.

**Fix.** Prima iterazione (2026-07-15): sottolineatura di `.tli-mosaic-post-meta a` — bocciata esteticamente. Revisione (2026-07-16): **distinzione cromatica, niente sottolineatura**. L'unico nodo mai segnalato era la categoria del box correlati sulle pagine articolo, dove le *strip* sono prive di stile proprio: lì il testo della strip ora eredita `#6c757d` (regola `.tli-mosaic-post-meta` in app.css, ininfluente su home/archivio dove mosaic-and-slider.css la sovrascrive) e i link restano `#17222B` ➡ **3.46:1 ≥ 3:1**, l'audit passa per contrasto. Home e archivio non sono mai stati segnalati — nemmeno col blu a 1.34:1 sul grigio: lì la categoria ha uno stile proprio (12px, maiuscolo, peso 500) e axe non la considera "link in un blocco di testo" ➡ il rischio *whack-a-mole* ipotizzato sopra non si è concretizzato; da ricontrollare a ogni aggiornamento di Lighthouse. Riverificato dopo la revisione: `link-in-text-block` ok su home e articolo, mobile e desktop.


### 8. 🟠 Alto — Viewport: `maximum-scale=1` bloccava lo zoom ✅ risolto (2026-07-15)

**Problema.** Il meta viewport di [base.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/base.html.twig) portava `maximum-scale=1` (ereditato dal tema Newspark): su **Android Chrome** il *pinch-zoom* era realmente bloccato — barriera WCAG 1.4.4 su un sito pieno di screenshot che i lettori ingrandiscono. iOS lo ignora dal 2016 e il desktop del tutto, quindi il blocco era anche incoerente tra piattaforme. Curiosità di misura: Lighthouse 13.4 non fallisce più questo audit, PSI (motore più vecchio) sì — il problema per gli utenti Android era reale a prescindere.

**Fix.** Meta ridotto a `width=device-width, initial-scale=1` (rimosso anche `shrink-to-fit=no`, workaround per Safari 9 morto da anni). Lo zoom **accidentale** — il vero fastidio, cioè il doppio-tap — è neutralizzato da `body { touch-action: manipulation }` in [app.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/app.css): niente *double-tap-to-zoom*, *pinch* deliberato preservato, nessun audit fallito su alcuna versione di Lighthouse. Il rischio di auto-zoom iOS sui campi *form* (l'unico uso legittimo di `maximum-scale=1`) non ci riguarda: gli input sono Bootstrap a 16px. Verificato dal vivo su dev0 (meta + CSS inline nella pagina servita).

**Residuo (non pubblico).** ✅ Chiuso il 2026-07-15: i due template di test ([test/index.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/test/index.html.twig), [test/new-home.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/test/new-home.html.twig)) sono stati allineati al meta di `base.html.twig` (`width=device-width, initial-scale=1`).


### 9. 🟠 Alto — Contrasto: rosa Bootstrap dei `<code>` ✅ risolto (2026-07-15)

**Problema.** Emerso durante la verifica dei fix 1–4: i `<code>` nel corpo articolo (output del plugin *istruzioni*) usano il rosa default di Bootstrap `#d63384`, che sul chip grigio di [article.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/article.css) rende **3.87:1**.

**Fix.** Override globale `code { color: #ab296a }` nel blocco di app.css — è il `$pink-600` della palette Bootstrap: 5.56:1 sul chip, 6.45:1 su bianco.


### 10. 🟠 Alto — Touch target insufficienti nello slideshow centrale della home ✅ risolto (2026-07-16)

**Problema.** Audit `target-size` (WCAG 2.5.8: minimo 24×24px o spaziatura equivalente), segnalato da PSI su `single-play-post-content`: nelle card grandi dello slideshow centrale di [home/index.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/home/index.html.twig) il link categoria è un target alto **16px** con **6px** di distanza dal link titolo sottostante (misurato su dev0, viewport 412px). Il Lighthouse 13.4 locale *passa* l'audit — stessa divergenza di motori già vista per `meta-viewport` — ma il problema per chi tocca è reale. I link nelle *strip* di metadati (12px) sono invece **esenti** per definizione: target inline in un blocco di testo.

**Fix.** `display: inline-block; padding: 6px 0` sul link categoria (app.css) ➡ target misurato da 16 a **36px**; resa visiva invariata (padding trasparente sull'overlay, il contenuto è ancorato in basso con ampio margine).


### 11. 🟡 Medio — Manca il landmark `<main>` ✅ risolto (2026-07-16)

**Problema.** Audit `landmark-one-main` (segnalato da PSI; il Lighthouse 13.4 locale lo passa): nessuna pagina aveva un landmark `main`, quindi gli *screen reader* non possono saltare direttamente al contenuto principale.

**Fix.** In [base.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/base.html.twig) il blocco `bodyMainContent` + paginatore è ora avvolto in `<main>`; userbar, header, ads, modali e footer restano fuori. Vale per tutte le pagine Symfony (phpBB è fuori ambito). Verificato su dev0: landmark presente, smoke test ok, nessun audit regredito.
