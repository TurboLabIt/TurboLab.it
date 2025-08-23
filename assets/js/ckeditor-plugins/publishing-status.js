// tliPublishingStatus.js
import { Plugin, createDropdown, ButtonView } from 'ckeditor5';
import ArticlePublishable from '../article-edit-publishable';


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
                { value: '0', label: '🚧 Bozza in lavorazione (nascosto al pubblico)' },
                { value: '3', label: '✅ Pronto e finito (visibile al pubblico)' },
                //{ value: '5', label: '(admin) Pubblicato' },
                //{ value: '7', label: '(admin) Bloccato/rimosso' }
            ];

            // Build the panel with plain ButtonViews (no Model/Collection needed)
            for (const { value, label } of items) {
                const itemBtn = new ButtonView(locale);
                itemBtn.set({
                    label,
                    withText: true
                });

                itemBtn.on('execute', () => {

                    ArticlePublishable.setPublishingStatus(value, function() {
                        dropdownView.buttonView.set({ label });
                    });

                    dropdownView.isOpen = false;
                });

                dropdownView.panelView.children.add(itemBtn);
            }

            return dropdownView;
        });
    }
}
