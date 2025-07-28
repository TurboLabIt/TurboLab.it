import { Plugin } from 'ckeditor5';
import { ButtonView } from 'ckeditor5';
import { Command } from 'ckeditor5';
import ArticleContentEditable from "../article-edit-contenteditable";

// A simple SVG icon for the button, representing "H2".
const h2Icon = '<svg width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 6V19M6 6H18" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';


export default class TliH2Plugin extends Plugin {
    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('h2', locale => {

            const view = new ButtonView(locale);

            view.set({
                label: 'Titolo',
                icon: saveIcon,
                tooltip: true,
                keystroke: 'Ctrl+1'
            });

            view.on('execute', () => {
                //ArticleContentEditable.save(null, editor.getData(), null);
            });

            view.on('render', () => {
                view.element.id = 'tli-ckeditor-h2';
            });

            return view;
        });
    }
}
