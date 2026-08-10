import { Plugin, ButtonView } from 'ckeditor5';
import ArticleContentEditable from './../article-edit-contenteditable';
import openAjaxModal from './../modal-ajax';


export default class TliAdvisePlugin extends Plugin {
    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliAdvise', locale => {

            const view = new ButtonView(locale);

            view.set({
                label: 'Verifica',
                icon: $('#tli-toolbar-icons .tli-advise-icon')[0].outerHTML,
                tooltip: true
            });

            view.on('execute', () => {

                // the check runs server-side on the saved article: verifying a stale copy would mislead
                if( ArticleContentEditable.hasUnsavedChanges() ) {

                    alert("⚠️ Ci sono modifiche non salvate.\n\nSalva l'articolo (Ctrl+S), poi avvia la verifica.");
                    return;
                }

                openAjaxModal( jQuery('article').attr('data-advise-url') );
            });

            return view;
        });
    }
}
