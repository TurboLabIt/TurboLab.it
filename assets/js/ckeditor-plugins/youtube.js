import { Plugin, ButtonView } from 'ckeditor5';

export default class TliYoutube extends Plugin {
    static get pluginName() { return 'TliYoutube'; }

    init() {
        const editor = this.editor;

        // Toolbar button
        editor.ui.componentFactory.add('tliyoutube', locale => {
            const view = new ButtonView(locale);
            view.set({
                icon: $('#tli-toolbar-icons .bi-youtube')[0].outerHTML,
                tooltip: 'Inserisci filmato YouTube',
                withText: false
            });

            view.on('execute', () => {
                const url = (window.prompt("Incolla qui l'URL del filmato da inserire") || '').trim();
                const m = url.match(/v=([^&$]+)/i);
                if (!m || !m[1]) {
                    window.alert("URL invalido! deve essere simile a\n\n\t http://www.youtube.com/watch?v=abcdef \n\n Correggi e riprova!");
                    return;
                }

                const id = m[1];
                const html =
                    `<iframe src="https://www.youtube-nocookie.com/embed/${id}?rel=0" frameborder="0" width="100%" height="540"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen></iframe>`;

                // Insert the raw HTML via the data pipeline.
                editor.model.change(() => {
                    const viewFragment = editor.data.processor.toView(html);
                    const modelFragment = editor.data.toModel(viewFragment);
                    editor.model.insertContent(modelFragment, editor.model.document.selection);
                });
            });

            return view;
        });
    }

    // Allow <iframe> and needed attributes
    afterInit() {
        const editor = this.editor;

        if (!editor.plugins.has('GeneralHtmlSupport')) return;

        const dataFilter = editor.plugins.get('DataFilter');
        if (!dataFilter) return;

        dataFilter.allowElement('iframe');
        dataFilter.allowAttributes({
            name: 'iframe',
            attributes: {
                src: true,
                frameborder: true,
                width: true,
                height: true,
                allow: true,
                allowfullscreen: true
            }
        });
    }
}
