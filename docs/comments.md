# [Commenti agli articoli](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/comments.md)

I commenti agli articoli **sono topic del forum phpBB**: si riusa così tutto il sistema-forum (posting, moderazione, BBCode, allegati, rank, notifiche, anti-spam).


## Modello: un articolo ➡ un topic nel forum commenti

Ogni articolo è collegato a **un** topic nel forum dedicato ai commenti ([`Forum::ID_COMMENTS`](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Entity/PhpBB/Forum.php)) tramite il campo `Article.commentsTopic` (FK `comments_topic_id` ➡ `topic_id`, vedi [`CommentsTopicableEntityTrait`](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Trait/CommentsTopicableEntityTrait.php)).

All'interno di quel topic:

- il primo post è un segnaposto che mostra titolo, spotlight e abstract dell'articolo
- i commenti veri sono le successive risposte

Le Entity di phpBB sono in sola lettura dal lato-Symfony: tutte le scritture sul primo post del topic passano perciò dalle *special page* (vedi sotto).


## Conteggio commenti

`Article::getCommentsNum()` − 1 (si scala il primo post segnaposto) = numero di commenti. Il valore è letto live dal topic (`topic_posts_approved`).

- nelle liste (home, archivio, ...) è renderizzato server-side
- sulla pagina articolo dello specifico articolo è invece sempre caricato in modo asincrono, insieme alla registrazione della visita ([`visit-on-load.js`](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/js/visit-on-load.js) ➡ odometer su `.tli-comments-num-target`)


## Ciclo di vita del topic dei commenti

1. **Creazione segnaposto** — [`ArticleEditor::createCommentsTopicPlaceholder()`](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/ArticleEditor.php): se l'articolo è visitabile e non ha ancora un topic, ne crea uno con un corpo provvisorio, e lo collega
2. **Marcatura "da aggiornare"** — al salvataggio, `ArticleEditor::save()` imposta `commentsTopicNeedsUpdate = YES` (stati `NO`/`YES`/`NEVER` in [`CommentsTopicStatusesTrait`](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Trait/CommentsTopicStatusesTrait.php); `NEVER` esclude permanentemente l'articolo dalla sincronizzazione)
3. **Sincronizzazione (via [cron](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/cron))** — `comment-topics-update.sh` ➡ [`CommentTopicsUpdateCommand`](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/CommentTopicsUpdateCommand.php): per ogni articolo `YES`, fa una POST alla special page `comments-topic-update/` (titolo + corpo BBCode da [`article/comments-topic.bbcode.twig`](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/article/comments-topic.bbcode.twig)) per riallineare il primo post, poi riporta lo stato a `NO`. Nello stesso giro azzera i riferimenti orfani (articoli che puntano a un topic non più esistente ➡ `commentsTopic = NULL`)
4. **Eliminazione dell'articolo** — `ArticleEditor::delete()`: se il topic non ha risposte, viene anch'esso eliminato. Se ha già delle risposte (`postNum > 1`) viene conservato


## Le special page

Tre script che girano nel contesto phpBB (rewrite in [config/custom/nginx.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf)):

- **[comments.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/special-pages/comments.php)** — `GET /ajax/commenti/{id}`, pubblica: renderizza l'HTML dei commenti di un topic. Verifica che il topic sia approvato, appartenga a `ID_COMMENTS` e non sia in `ID_OFFLIMIT`; seleziona tutte le risposte approvate tranne il primo post, le arricchisce (rank, allegati, data "amichevole", BBCode renderizzato) e restituisce un frammento HTML con i link *Rispondi* / *Rispondi citando*
- **[comments-topic-update.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/special-pages/comments-topic-update.php)** — `POST /comments-topic-update/`, solo da `127.0.0.1`: riallinea titolo e primo post del topic all'articolo, impersonando l'autore; un fixup SQL manuale sistema i campi `topic_*poster*` che `submit_post` non aggiorna
- **[comments-topic-delete.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/special-pages/comments-topic-delete.php)** — `DELETE /comments-topic-delete/`, solo `127.0.0.1`: elimina il topic con `delete_topics()`

> ⚠️ **Visibilità dei commenti** — `comments.php` filtra le risposte su `post_visibility = ITEM_APPROVED`. In passato filtrava anche `post_delete_time = 0`, ed era sbagliato: phpBB scrive `post_delete_time`/`post_delete_user` a ogni transizione di visibilità, inclusa l'approvazione di un post uscito dalla coda di moderazione (i primi post dei nuovi utenti). Quel filtro faceva quindi sparire dalle pagine articolo i commenti *approvati* dei nuovi utenti — che però restavano visibili nel forum, perché phpBB per il display guarda solo `post_visibility`.


## Caricamento lato-client

Sulla pagina articolo ([`assets/js/comments.js`](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/js/comments.js), entry point `article`):

1. il box `.post-comments-list` si carica tramite lazy-load quando entra nel viewport: `GET` all'URL in `data-comments-loading-url` (l'endpoint `/ajax/commenti/{id}`)
2. l'HTML restituito viene iniettato così com'è via `.html()` (same-origin verificato lato JS). Attenzione: non è tutto "HTML phpBB fidato" — il corpo dei commenti e gli allegati sì (via `generate_text_for_display` / `parse_attachments`), ma lo username viene *escaped* da `comments.php` stesso con [`HtmlProcessorBase::encode()`](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/HtmlProcessorBase.php)

Se i commenti sono `0`, `getCommentsAjaxLoadingUrl()` restituisce `null` e nessuna chiamata viene effettuata.


## 🔗 Vedi anche

- [Integrazione con il forum](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/forum-integration.md)
- [Utenti, phpBB e sola-lettura da Symfony](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/users.md)
- [Integrazione issue GitHub](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/bug.md) — stesso pattern di scrittura su phpBB tramite special page
