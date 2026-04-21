import { Plugin, ButtonView } from 'ckeditor5';


export default class TliLinkArticle extends Plugin {
    static get pluginName() { return 'TliLinkArticle'; }

    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliLinkArticle', locale => {
            const view = new ButtonView(locale);
            view.set({
                icon: $('#tli-toolbar-icons .tli-link-article-icon')[0].outerHTML,
                tooltip: 'Inserisci link ad articolo (Ctrl+L)',
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

    function setModalControlsDisabled(modalFrame, disabled) {
        modalFrame.find('.tli-link-article-search-input, .tli-link-article-search-btn, input[name="article-format"], #tli-link-article-mine-only, input[name="article-sort"]')
            .prop('disabled', disabled);
    }

    function updateRelevanceRadioState(modalFrame) {
        const query = modalFrame.find('.tli-link-article-search-input').val().trim();
        const relevanceRadio = modalFrame.find('#tli-link-article-sort-relevance');
        relevanceRadio.prop('disabled', query === '');
        if( query === '' && relevanceRadio.is(':checked') ) {
            modalFrame.find('#tli-link-article-sort-date').prop('checked', true);
        }
    }

    function performSearch(modalFrame) {

        const query = modalFrame.find('.tli-link-article-search-input').val().trim();

        const resultsContainer = modalFrame.find('.tli-link-article-results');
        resultsContainer.html(
            '<div class="d-flex justify-content-center p-3">' +
                '<i class="fa-solid fa-spinner fa-spin-pulse fa-2xl"></i>' +
            '</div>'
        );

        setModalControlsDisabled(modalFrame, true);

        const format    = modalFrame.find('input[name="article-format"]:checked').val() || '';
        const onlyMine  = modalFrame.find('#tli-link-article-mine-only').is(':checked') ? '1' : '';
        const sort      = modalFrame.find('input[name="article-sort"]:checked').val() || '';

        const params = new URLSearchParams();
        if( format )   params.set('format', format);
        if( onlyMine ) params.set('only-mine', '1');

        let endpoint;
        if( query.length >= 2 ) {
            endpoint = '/cerca/ajax/link-article/' + encodeURIComponent(query);
            if( sort === 'date' ) params.set('sort', 'date');
        } else {
            endpoint = '/cerca/ajax/link-article-latest';
        }

        const qs = params.toString();
        if( qs ) endpoint += '?' + qs;

        return jQuery.get(endpoint, function(html) {
            resultsContainer.html(html);
        }).fail(function(jqXHR) {
            resultsContainer.html('<p class="alert alert-danger">' + (jqXHR.responseText || 'Errore durante la ricerca') + '</p>');
        }).always(function() {
            setModalControlsDisabled(modalFrame, false);
            updateRelevanceRadioState(modalFrame);
        });
    }


    let articleSearchDebounceTimer;

    function triggerSearchImmediate(modalFrame) {
        clearTimeout(articleSearchDebounceTimer);
        performSearch(modalFrame);
    }

    // Search button click
    jQuery(document).on('click', '.tli-link-article-search-btn', function() {
        triggerSearchImmediate( jQuery(this).closest('.modal') );
    });

    // Enter key in search input
    jQuery(document).on('keydown', '.tli-link-article-search-input', function(event) {
        if( event.key === 'Enter' ) {
            event.preventDefault();
            triggerSearchImmediate( jQuery(this).closest('.modal') );
        }
    });

    // Auto-search on filter change
    jQuery(document).on('change', '#tli-link-article-modal input[name="article-format"], #tli-link-article-mine-only, #tli-link-article-modal input[name="article-sort"]', function() {
        triggerSearchImmediate( jQuery(this).closest('.modal') );
    });

    // Debounced auto-search 1s after the last keystroke
    jQuery(document).on('input', '.tli-link-article-search-input', function() {
        const modalFrame = jQuery(this).closest('.modal');
        updateRelevanceRadioState(modalFrame);
        clearTimeout(articleSearchDebounceTimer);
        articleSearchDebounceTimer = setTimeout(function() {
            performSearch(modalFrame).always(function() {
                modalFrame.find('.tli-link-article-search-input').trigger('focus');
            });
        }, 1000);
    });

    // Eager preload on page load, while the modal is still hidden
    const articleModalFrame = jQuery('#tli-link-article-modal');
    if( articleModalFrame.length ) {
        updateRelevanceRadioState(articleModalFrame);
        performSearch(articleModalFrame);
    }


    // "Crea collegamento" — insert selected text as link, or article title as link
    jQuery(document).on('click', '.tli-link-article-insert-link', function() {

        const resultRow     = jQuery(this).closest('.tli-link-article-result');
        const articleUrl    = resultRow.data('article-url');
        const articleTitle  = resultRow.data('article-title');
        const modalFrame    = jQuery('#tli-link-article-modal');
        const selectedText  = modalFrame.data('selected-text') || '';

        const editable = document.querySelector('.ck-editor__editable');
        if( editable && editable.ckeditorInstance ) {

            const editor = editable.ckeditorInstance;

            if( selectedText !== '' ) {
                // Text already in the document — just apply the link attribute,
                // preserving surrounding formatting (ins, bold, etc.)
                editor.execute('link', articleUrl);
            } else {
                // No selection — insert the article title as linked text,
                // inheriting any attributes active at the cursor (ins, bold, etc.)
                editor.model.change(writer => {
                    const selection = editor.model.document.selection;
                    const attrs = {};
                    for( const [key, value] of selection.getAttributes() ) {
                        attrs[key] = value;
                    }
                    attrs.linkHref = articleUrl;
                    const text = writer.createText(articleTitle, attrs);
                    editor.model.insertContent(text, selection);
                });
            }

            editor.editing.view.focus();
        }

        bootstrap.Modal.getInstance(modalFrame[0]).hide();
    });


    // "Inserisci come » Leggi: ... + Immagine" — insert formatted block with spotlight
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


    // "Inserisci come » Leggi:" — insert formatted block without image
    jQuery(document).on('click', '.tli-link-article-insert-leggi-no-img', function() {

        const resultRow     = jQuery(this).closest('.tli-link-article-result');
        const articleUrl    = resultRow.data('article-url');
        const articleTitle  = resultRow.data('article-title');
        const modalFrame    = jQuery('#tli-link-article-modal');

        const editable = document.querySelector('.ck-editor__editable');
        if( editable && editable.ckeditorInstance ) {

            const editor = editable.ckeditorInstance;

            editor.model.change(writer => {

                const selection = editor.model.document.selection;

                // Inherit text attributes active at the cursor (ins, etc.) but
                // drop any pre-existing link so the article URL doesn't clash.
                const inheritedAttrs = {};
                for( const [key, value] of selection.getAttributes() ) {
                    if( key !== 'linkHref' ) {
                        inheritedAttrs[key] = value;
                    }
                }

                const paragraph = writer.createElement('paragraph');
                writer.appendText('» Leggi:', Object.assign({}, inheritedAttrs, { bold: true }), paragraph);
                writer.appendText(' ', inheritedAttrs, paragraph);
                writer.appendText(articleTitle, Object.assign({}, inheritedAttrs, { linkHref: articleUrl }), paragraph);

                editor.model.insertContent(paragraph, selection);
            });

            editor.editing.view.focus();
        }

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
