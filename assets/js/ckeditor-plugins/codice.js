import { Plugin, CodeBlock } from 'ckeditor5';

/**
 * "Codice" button: the built-in CodeBlock dropdown (<pre><code>), re-skinned. The main part
 * inserts/toggles a block repeating the last language chosen (default: "Testo semplice", no
 * class, no highlighting); the arrow opens the language list built in article-edit-ckeditor.js
 * from the server-provided allowlist.
 */
export default class TliCodicePlugin extends Plugin {
    static get requires() {
        return [CodeBlock];
    }

    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliCodice', () => {

            const dropdownView = editor.ui.componentFactory.create('codeBlock');

            dropdownView.buttonView.set({
                label: 'Codice',
                icon: $('#tli-toolbar-icons .bi-code-square')[0].outerHTML,
                tooltip: 'Inserisci un blocco di codice'
            });

            return dropdownView;
        });
    }
}
