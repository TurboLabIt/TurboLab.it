import { Plugin, ClipboardPipeline } from 'ckeditor5';

/**
 * Tables are accepted only if Aticle::isAllowExtendedHtml. Everywhere else, the server-side
 * allowlist strips them on save, concatenating every cell into a run-on paragraph with no separator —
 * silently, so the author only finds out after publishing. Refuse the paste instead, and say why.
 *
 * Loaded only when the article is NOT Article::isAllowExtendedHtml: see article-edit-ckeditor.js
 */
export default class TliTablePasteGuard extends Plugin {
    static get pluginName() { return 'TliTablePasteGuard'; }

    static get requires() { return [ClipboardPipeline]; }

    init() {
        const editor = this.editor;

        // 'highest': run before PasteFromOffice rewrites the fragment
        editor.plugins.get('ClipboardPipeline').on('inputTransformation', (evt, data) => {

            if( !containsTable(data.content) ) {
                return;
            }

            evt.stop();

            window.alert(
                "📅 Il testo che stai incollando contiene una tabella.\n\n" +
                "Le tabelle non sono generalmente ammesse negli articoli di TurboLab.it, per evitare impaginazioni \"artistiche\". " +
                "Puoi procedere in due modi:\n\n" +
                " * riscrivi il contenuto della tabella come elenco puntato (consigliato)\n" +
                " * contatta un admin richiedendo l'impostazione dell'attributo Article::isAllowExtendedHtml"
            );

        }, { priority: 'highest' });
    }
}


/**
 * Depth-first search for a <table> in a view DocumentFragment. Text nodes have no getChildren().
 */
function containsTable(viewNode)
{
    if( viewNode.is && viewNode.is('element', 'table') ) {
        return true;
    }

    if( typeof viewNode.getChildren !== 'function' ) {
        return false;
    }

    for( const child of viewNode.getChildren() ) {

        if( containsTable(child) ) {
            return true;
        }
    }

    return false;
}
