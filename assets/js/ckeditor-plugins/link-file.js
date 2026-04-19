import { Plugin, ButtonView } from 'ckeditor5';


export default class TliLinkFile extends Plugin {
    static get pluginName() { return 'TliLinkFile'; }

    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliLinkFile', locale => {
            const view = new ButtonView(locale);
            view.set({
                icon: $('#tli-toolbar-icons .tli-link-file-icon')[0].outerHTML,
                tooltip: 'Inserisci link a file (Ctrl+D)',
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

                const modalFrame = jQuery('#tli-link-file-modal');
                modalFrame.data('selected-text', selectedText.trim());

                new bootstrap.Modal(modalFrame).show();

                setTimeout(() => {
                    modalFrame.find('.tli-link-file-search-input').trigger('focus');
                }, 500);
            });

            return view;
        });
    }
}


jQuery(function() {

    function setModalControlsDisabled(modalFrame, disabled) {
        modalFrame.find('.tli-link-file-search-input, .tli-link-file-search-btn, #tli-link-file-mine-only, input[name="file-sort"]')
            .prop('disabled', disabled);
    }

    function performSearch(modalFrame) {

        const query = modalFrame.find('.tli-link-file-search-input').val().trim();
        if( query.length < 2 ) {
            return;
        }

        const resultsContainer = modalFrame.find('.tli-link-file-results');
        resultsContainer.html(
            '<div class="d-flex justify-content-center p-3">' +
                '<i class="fa-solid fa-spinner fa-spin-pulse fa-2xl"></i>' +
            '</div>'
        );

        setModalControlsDisabled(modalFrame, true);

        const onlyMine  = modalFrame.find('#tli-link-file-mine-only').is(':checked') ? '1' : '';
        const sort      = modalFrame.find('input[name="file-sort"]:checked').val() || '';

        let endpoint = '/cerca/ajax/link-file/' + encodeURIComponent(query);
        const params = new URLSearchParams();
        if( onlyMine )          params.set('only-mine', '1');
        if( sort === 'date' )   params.set('sort', 'date');
        const qs = params.toString();
        if( qs ) endpoint += '?' + qs;

        jQuery.get(endpoint, function(html) {
            resultsContainer.html(html);
        }).fail(function(jqXHR) {
            resultsContainer.html('<p class="alert alert-danger">' + (jqXHR.responseText || 'Errore durante la ricerca') + '</p>');
        }).always(function() {
            setModalControlsDisabled(modalFrame, false);
        });
    }


    // Search button click
    jQuery(document).on('click', '.tli-link-file-search-btn', function() {
        performSearch( jQuery(this).closest('.modal') );
    });

    // Enter key in search input
    jQuery(document).on('keydown', '.tli-link-file-search-input', function(event) {
        if( event.key === 'Enter' ) {
            event.preventDefault();
            performSearch( jQuery(this).closest('.modal') );
        }
    });

    // Auto-search on filter change
    jQuery(document).on('change', '#tli-link-file-mine-only, #tli-link-file-modal input[name="file-sort"]', function() {
        performSearch( jQuery(this).closest('.modal') );
    });


    // "Crea collegamento" — insert selected text as link, or file name as link
    jQuery(document).on('click', '.tli-link-file-insert-link', function() {

        const resultRow     = jQuery(this).closest('.tli-link-file-result');
        const fileUrl       = resultRow.data('file-url');
        const fileName      = resultRow.data('file-name');
        const modalFrame    = jQuery('#tli-link-file-modal');
        const selectedText  = modalFrame.data('selected-text') || '';

        const editable = document.querySelector('.ck-editor__editable');
        if( editable && editable.ckeditorInstance ) {

            const editor = editable.ckeditorInstance;

            if( selectedText !== '' ) {
                // Text already in the document — just apply the link attribute,
                // preserving surrounding formatting (ins, bold, etc.)
                editor.execute('link', fileUrl);
            } else {
                // No selection — insert the file name as linked text,
                // inheriting any attributes active at the cursor (ins, bold, etc.)
                editor.model.change(writer => {
                    const selection = editor.model.document.selection;
                    const attrs = {};
                    for( const [key, value] of selection.getAttributes() ) {
                        attrs[key] = value;
                    }
                    attrs.linkHref = fileUrl;
                    const text = writer.createText(fileName, attrs);
                    editor.model.insertContent(text, selection);
                });
            }

            editor.editing.view.focus();
        }

        bootstrap.Modal.getInstance(modalFrame[0]).hide();
    });


    // "Inserisci come » Download:" — insert formatted block
    jQuery(document).on('click', '.tli-link-file-insert-download', function() {

        const resultRow     = jQuery(this).closest('.tli-link-file-result');
        const fileUrl       = resultRow.data('file-url');
        const fileName      = resultRow.data('file-name');
        const modalFrame    = jQuery('#tli-link-file-modal');

        const editable = document.querySelector('.ck-editor__editable');
        if( editable && editable.ckeditorInstance ) {

            const editor = editable.ckeditorInstance;

            editor.model.change(writer => {

                const selection = editor.model.document.selection;

                // Inherit text attributes active at the cursor (ins, etc.) but
                // drop any pre-existing link so the file URL doesn't clash.
                const inheritedAttrs = {};
                for( const [key, value] of selection.getAttributes() ) {
                    if( key !== 'linkHref' ) {
                        inheritedAttrs[key] = value;
                    }
                }

                const paragraph = writer.createElement('paragraph');
                writer.appendText('» Download:', Object.assign({}, inheritedAttrs, { bold: true }), paragraph);
                writer.appendText(' ', inheritedAttrs, paragraph);
                writer.appendText(fileName, Object.assign({}, inheritedAttrs, { linkHref: fileUrl }), paragraph);

                editor.model.insertContent(paragraph, selection);
            });

            editor.editing.view.focus();
        }

        bootstrap.Modal.getInstance(modalFrame[0]).hide();
    });
});
