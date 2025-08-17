// tliPublishingStatus.js
import { Plugin, createDropdown, ButtonView } from 'ckeditor5';

export default class TliPublishingStatus extends Plugin {
    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliPublishingStatus', locale => {
            const dropdownView = createDropdown(locale);

            // Initial button label
            dropdownView.buttonView.set({
                label: 'Stato di pubblicazione',
                withText: true,
                tooltip: true
            });

            const items = [
                { value: '0', label: 'In lavorazione (bozza)' },
                { value: '3', label: 'Finito e visibile a tutti' },
                { value: '5', label: 'Pubblicato' },
                { value: '7', label: 'Non disponibile (bloccato/rimosso)' }
            ];

            // Build the panel with plain ButtonViews (no Model/Collection needed)
            for (const { value, label } of items) {
                const itemBtn = new ButtonView(locale);
                itemBtn.set({
                    label,
                    withText: true
                });

                itemBtn.on('execute', () => {
                    alert(value);
                    dropdownView.buttonView.set({ label }); // reflect selection on the toolbar button
                    dropdownView.isOpen = false;            // close the dropdown after picking
                });

                dropdownView.panelView.children.add(itemBtn);
            }

            return dropdownView;
        });
    }
}
