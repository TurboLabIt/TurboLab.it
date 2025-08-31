import { Plugin, ButtonView } from 'ckeditor5';
import ArticleContentEditable from './../article-edit-contenteditable';


export default class TliSavePlugin extends Plugin {
    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('save', locale => {

            const view = new ButtonView(locale);

            view.set({
                label: 'Salva',
                icon: $('#tli-toolbar-icons .tli-floppy-icon')[0].outerHTML,
                tooltip: true,
                keystroke: 'Ctrl+S'
            });

            view.on('execute', () => {
                ArticleContentEditable.save(null, editor.getData(), null);
            });

            view.on('render', () => {
                view.element.id = 'tli-ckeditor-save';
            });

            return view;
        });
    }
}
