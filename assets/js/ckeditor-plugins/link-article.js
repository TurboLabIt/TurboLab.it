import { Plugin, ButtonView } from 'ckeditor5';


export default class TliLinkArticle extends Plugin {
    static get pluginName() { return 'TliLinkArticle'; }

    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliLinkArticle', locale => {
            const view = new ButtonView(locale);
            view.set({
                icon: $('#tli-toolbar-icons .tli-link-article-icon')[0].outerHTML,
                tooltip: 'Inserisci link ad articolo',
                withText: false
            });

            view.on('execute', () => {

                // Capture the selected text BEFORE opening the modal (focus will shift)
                const selection = editor.model.document.selection;
                let selectedText = '';
                for (const range of selection.getRanges()) {
                    for (const item of range.getItems()) {
                        if (item.is('$text') || item.is('$textProxy')) {
                            selectedText += item.data;
                        }
                    }
                }

                const modalFrame = jQuery('#tli-link-article-modal');
                modalFrame.data('selected-text', selectedText.trim());

                const articleFormat = jQuery('article').data('format') || 1;
                modalFrame.find('input[name="article-format"][value="' + articleFormat + '"]').prop('checked', true);

                new bootstrap.Modal(modalFrame).show();

                setTimeout(() => {
                    modalFrame.find('.tli-link-article-search-input').trigger('focus');
                }, 500);
            });

            return view;
        });
    }
}


jQuery(function() {

    function performSearch(modalFrame) {

        const query = modalFrame.find('.tli-link-article-search-input').val().trim();
        if( query.length < 2 ) {
            return;
        }

        const resultsContainer = modalFrame.find('.tli-link-article-results');
        resultsContainer.html(
            '<div class="d-flex justify-content-center p-3">' +
                '<i class="fa-solid fa-spinner fa-spin-pulse fa-2xl"></i>' +
            '</div>'
        );

        const endpoint = '/cerca/ajax/link-article/' + encodeURIComponent(query);
        jQuery.get(endpoint, function(html) {
            resultsContainer.html(html);
        }).fail(function(jqXHR) {
            resultsContainer.html('<p class="alert alert-danger">' + (jqXHR.responseText || 'Errore durante la ricerca') + '</p>');
        });
    }


    // Search button click
    jQuery(document).on('click', '.tli-link-article-search-btn', function() {
        performSearch( jQuery(this).closest('.modal') );
    });

    // Enter key in search input
    jQuery(document).on('keydown', '.tli-link-article-search-input', function(event) {
        if( event.key === 'Enter' ) {
            event.preventDefault();
            performSearch( jQuery(this).closest('.modal') );
        }
    });


    // "Crea collegamento" — insert selected text as link, or article title as link
    jQuery(document).on('click', '.tli-link-article-insert-link', function() {

        const resultRow     = jQuery(this).closest('.tli-link-article-result');
        const articleUrl    = resultRow.data('article-url');
        const articleTitle  = resultRow.data('article-title');
        const modalFrame    = jQuery('#tli-link-article-modal');
        const selectedText  = modalFrame.data('selected-text') || '';
        const linkText      = selectedText !== '' ? selectedText : articleTitle;

        const html = `<a href="${articleUrl}">${linkText}</a>`;
        insertHtmlIntoEditor(html);

        bootstrap.Modal.getInstance(modalFrame[0]).hide();
    });


    // "Inserisci come » Leggi:" — insert formatted block
    jQuery(document).on('click', '.tli-link-article-insert-leggi', function() {

        const resultRow     = jQuery(this).closest('.tli-link-article-result');
        const articleUrl    = resultRow.data('article-url');
        const articleTitle  = resultRow.data('article-title');
        const articleSpot   = resultRow.data('article-spotlight');
        const modalFrame    = jQuery('#tli-link-article-modal');

        const html =
            `<p><strong>» Leggi:</strong> <a href="${articleUrl}">${articleTitle}</a></p>` +
            `<p><a href="${articleUrl}"><img src="${articleSpot}"></a></p>`;
        insertHtmlIntoEditor(html);

        bootstrap.Modal.getInstance(modalFrame[0]).hide();
    });


    function insertHtmlIntoEditor(html) {

        // Access the CKEditor instance from the editable element
        const editable = document.querySelector('.ck-editor__editable');
        if( !editable || !editable.ckeditorInstance ) {
            return;
        }

        const editor = editable.ckeditorInstance;
        editor.model.change(() => {
            const viewFragment = editor.data.processor.toView(html);
            const modelFragment = editor.data.toModel(viewFragment);
            editor.model.insertContent(modelFragment, editor.model.document.selection);
        });

        editor.editing.view.focus();
    }
});
