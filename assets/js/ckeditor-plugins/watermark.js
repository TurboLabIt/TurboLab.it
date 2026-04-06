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

                    // Update label and close dropdown before DOM manipulation
                    dropdownView.buttonView.set({ label });
                    dropdownView.isOpen = false;

                    // Add spinner overlay positioned over the image, appended to body
                    // (CKEditor re-renders its widget DOM, so we can't append to the figure)
                    const figure = document.querySelector('figure.image.ck-widget.ck-widget_selected');
                    let overlay = null;
                    if (figure) {
                        const rect = figure.getBoundingClientRect();
                        overlay = document.createElement('div');
                        overlay.style.cssText = `position:fixed;top:${rect.top}px;left:${rect.left}px;` +
                            `width:${rect.width}px;height:${rect.height}px;` +
                            'background:rgba(255,255,255,0.7);display:flex;align-items:center;justify-content:center;z-index:9999';
                        overlay.innerHTML = '<div class="spinner-border text-primary" role="status"></div>';
                        document.body.appendChild(overlay);
                    }

                    jQuery.post(endpoint, {}, function () {

                        // Reload the image bypassing browser cache
                        const cacheBuster = '?t=' + Date.now();
                        const baseSrc = imgSrc.replace(/\?.*$/, '');

                        // Preload the new image, then update the editor
                        const tempImg = new Image();
                        tempImg.onload = tempImg.onerror = function () {
                            if (overlay) overlay.remove();
                            editor.model.change(writer => {
                                writer.setAttribute('src', baseSrc + cacheBuster, selectedElement);
                            });
                        };
                        tempImg.src = baseSrc + cacheBuster;

                    }).fail(function (jqXHR) {
                        if (overlay) overlay.remove();
                        alert(jqXHR.responseText);
                    });
                });

                dropdownView.panelView.children.add(itemBtn);
            }

            return dropdownView;
        });
    }
}
