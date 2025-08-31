import { Plugin, ButtonView, Command } from 'ckeditor5';

class TliUpdateEditing extends Plugin {
    init() {
        const editor = this.editor;

        editor.model.schema.extend('$text', { allowAttributes: 'ins' });

        // 👇 This makes RemoveFormat handle your custom attribute.
        editor.model.schema.setAttributeProperties('ins', { isFormatting: true });

        editor.conversion.for('upcast').elementToAttribute({
            view: 'ins',
            model: { key: 'ins', value: true }
        });

        editor.conversion.for('downcast').attributeToElement({
            model: 'ins',
            view: (value, { writer }) =>
                writer.createAttributeElement('ins', {}, { priority: 5 })
        });

        editor.commands.add('tliUpdate', new TliUpdateCommand(editor));
    }
}


class TliUpdateCommand extends Command {
    refresh() {
        const selection = this.editor.model.document.selection;
        this.isEnabled = this.editor.model.schema.checkAttributeInSelection( selection, 'ins' );
        this.value = selection.hasAttribute( 'ins' );
    }

    execute() {
        const editor = this.editor;
        const model = editor.model;
        const selection = model.document.selection;
        const isActive = selection.hasAttribute( 'ins' );

        model.change( writer => {
            // Apply to current selection ranges (so existing text changes immediately).
            const ranges = model.schema.getValidRanges( selection.getRanges(), 'ins' );

            if ( isActive ) {
                for ( const range of ranges ) {
                    writer.removeAttribute( 'ins', range );
                }
                // Also stop applying to newly typed text.
                writer.removeSelectionAttribute( 'ins' );
            } else {
                for ( const range of ranges ) {
                    writer.setAttribute( 'ins', true, range );
                }
                // Keep it active for newly typed text.
                writer.setSelectionAttribute( 'ins', true );
            }
        } );
    }
}


class TliUpdateUI extends Plugin {
    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliUpdate', locale => {
            const view = new ButtonView(locale);
            const command = editor.commands.get('tliUpdate');

            view.set({
                label: 'UPD',
                withText: true,
                tooltip: 'Aggiornamento'
            });

            view.bind('isOn').to(command, 'value');
            view.bind('isEnabled').to(command, 'isEnabled');

            this.listenTo(view, 'execute', () => editor.execute('tliUpdate'));

            return view;
        });
    }
}


export default class TliUpdatePlugin extends Plugin {
    static get requires() {
        return [TliUpdateEditing, TliUpdateUI];
    }
}
