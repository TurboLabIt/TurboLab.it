import { Plugin, ButtonView } from 'ckeditor5';
import ArticleAdvise from './../article-edit-advise';


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
                ArticleAdvise.run();
            });

            return view;
        });
    }
}
