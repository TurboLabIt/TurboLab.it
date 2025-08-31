import { Plugin, ButtonView, Command } from 'ckeditor5';

class TliIstruzioniEditing extends Plugin {
    init() {
        const editor = this.editor;

        editor.model.schema.extend('$text', { allowAttributes: 'code' });

        // 👇 This makes RemoveFormat handle your custom attribute.
        editor.model.schema.setAttributeProperties('code', { isFormatting: true });

        editor.conversion.for('upcast').elementToAttribute({
            view: 'code',
            model: { key: 'code', value: true }
        });

        editor.conversion.for('downcast').attributeToElement({
            model: 'code',
            view: (value, { writer }) =>
                writer.createAttributeElement('code', {}, { priority: 5 })
        });

        editor.commands.add('tliIstruzioni', new TliIstruzioniCommand(editor));
    }
}


class TliIstruzioniCommand extends Command {
    refresh() {
        const selection = this.editor.model.document.selection;
        this.isEnabled = this.editor.model.schema.checkAttributeInSelection( selection, 'code' );
        this.value = selection.hasAttribute( 'code' );
    }

    execute() {
        const editor = this.editor;
        const model = editor.model;
        const selection = model.document.selection;
        const isActive = selection.hasAttribute( 'code' );

        model.change( writer => {
            // Apply to current selection ranges (so existing text changes immediately).
            const ranges = model.schema.getValidRanges( selection.getRanges(), 'code' );

            if ( isActive ) {
                for ( const range of ranges ) {
                    writer.removeAttribute( 'code', range );
                }
                // Also stop applying to newly typed text.
                writer.removeSelectionAttribute( 'code' );
            } else {
                for ( const range of ranges ) {
                    writer.setAttribute( 'code', true, range );
                }
                // Keep it active for newly typed text.
                writer.setSelectionAttribute( 'code', true );
            }
        } );
    }
}


class TliIstruzioniUI extends Plugin {
    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliIstruzioni', locale => {
            const view = new ButtonView(locale);
            const command = editor.commands.get('tliIstruzioni');

            view.set({
                label: 'ISTR',
                withText: true,
                tooltip: 'Istruzioni (Ctrl+M)'
            });

            view.bind('isOn').to(command, 'value');
            view.bind('isEnabled').to(command, 'isEnabled');

            this.listenTo(view, 'execute', () => editor.execute('tliIstruzioni'));

            return view;
        });
    }
}


export default class TliIstruzioniPlugin extends Plugin {
    static get requires() {
        return [TliIstruzioniEditing, TliIstruzioniUI];
    }
}
