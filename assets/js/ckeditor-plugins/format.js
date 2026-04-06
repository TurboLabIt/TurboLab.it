import { Plugin, createDropdown, ButtonView } from 'ckeditor5';
import StatusBar from '../article-edit-statusbar';


export default class TliFormatPlugin extends Plugin {
    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliFormat', locale => {

            const dropdownView = createDropdown(locale);

            const choices = [
                { value: '1', label: '📖 Articolo, guida' },
                { value: '2', label: '📰 Notizia, segnalazione' }
            ];

            // Set initial label from <article data-format="...">
            const currentFormat = document.querySelector('article')?.dataset.format;
            const currentChoice = choices.find(c => c.value === currentFormat);

            dropdownView.buttonView.set({
                label: currentChoice ? currentChoice.label : 'Formato',
                withText: true,
                tooltip: false
            });

            dropdownView.buttonView.actionView = undefined;

            for (const { value, label } of choices) {

                const itemBtn = new ButtonView(locale);
                itemBtn.set({ label, withText: true });

                itemBtn.on('execute', () => {

                    const articleId = document.body.dataset.tliCmsId;
                    const endpoint = `/ajax/editor/article/${articleId}/change-format/${value}`;

                    dropdownView.buttonView.set({ label });
                    dropdownView.isOpen = false;

                    jQuery.post(endpoint, {}, function (jsonResponse) {

                        StatusBar.setSaved(jsonResponse.message);
                        document.querySelector('article').dataset.format = value;

                    }).fail(function (jqXHR, jsonResponse) {

                        StatusBar.setError(jqXHR, jsonResponse);
                    });
                });

                dropdownView.panelView.children.add(itemBtn);
            }

            return dropdownView;
        });
    }
}
