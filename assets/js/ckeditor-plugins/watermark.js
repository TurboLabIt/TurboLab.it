import { Plugin, createDropdown, ButtonView } from 'ckeditor5';


export default class TliWatermark extends Plugin {
    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliWatermark', locale => {

            const dropdownView = createDropdown(locale);

            dropdownView.buttonView.set({
                label: 'Watermark',
                withText: true,
                tooltip: false
            });

            dropdownView.buttonView.actionView = undefined;

            const choices = [
                { value: 4, label: 'In basso a sinistra (default)' },
                { value: 3, label: 'In basso a destra' },
                { value: 0, label: 'Disattiva watermark' }
            ];

            for (const { value, label } of choices) {

                const itemBtn = new ButtonView(locale);
                itemBtn.set({ label, withText: true });

                itemBtn.on('execute', () => {

                    const selectedElement = editor.model.document.selection.getSelectedElement();
                    if (!selectedElement || selectedElement.name !== 'imageBlock') {
                        dropdownView.isOpen = false;
                        return;
                    }

                    const imgSrc = selectedElement.getAttribute('src');
                    const imageIdMatch = imgSrc.match(/-(\d+)\.[^.]+$/);
                    if (!imageIdMatch) {
                        alert('Impossibile estrarre l\'ID immagine dall\'URL');
                        dropdownView.isOpen = false;
                        return;
                    }

                    const imageId = imageIdMatch[1];
                    const articleId = document.body.dataset.tliCmsId;
                    const endpoint = `/ajax/editor/article/${articleId}/images/${imageId}/watermark/${value}`;

                    jQuery.post(endpoint, {}, function () {

                        // Reload the image bypassing browser cache
                        const cacheBuster = '?t=' + Date.now();
                        const baseSrc = imgSrc.replace(/\?.*$/, '');

                        editor.model.change(writer => {
                            writer.setAttribute('src', baseSrc + cacheBuster, selectedElement);
                        });

                    }).fail(function (jqXHR) {
                        alert(jqXHR.responseText);
                    });

                    dropdownView.isOpen = false;
                });

                dropdownView.panelView.children.add(itemBtn);
            }

            return dropdownView;
        });
    }
}
