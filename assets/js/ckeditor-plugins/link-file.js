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

    function updateRelevanceRadioState(modalFrame) {
        const query = modalFrame.find('.tli-link-file-search-input').val().trim();
        const relevanceRadio = modalFrame.find('#tli-link-file-sort-relevance');
        relevanceRadio.prop('disabled', query === '');
        if( query === '' && relevanceRadio.is(':checked') ) {
            modalFrame.find('#tli-link-file-sort-date').prop('checked', true);
        }
    }

    function setSearchState(modalFrame, state) {
        modalFrame.attr('data-search-state', state);
        const completed = (state === 'completed');
        modalFrame.find('.tli-link-file-create-via-url, .tli-link-file-create-via-upload')
            .prop('disabled', !completed);
        modalFrame.find('.tli-link-file-create-hint').toggleClass('d-none', completed);
    }

    function insertDownloadLink(fileUrl, fileTitle) {
        const editable = document.querySelector('.ck-editor__editable');
        if( !editable || !editable.ckeditorInstance ) {
            return;
        }
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
            writer.appendText(fileTitle, Object.assign({}, inheritedAttrs, { linkHref: fileUrl }), paragraph);

            editor.model.insertContent(paragraph, selection);
        });

        editor.editing.view.focus();
    }

    function parseFormatFromUrl(rawUrl) {
        try {
            const u = new URL(rawUrl);
            const lastSegment = u.pathname.split('/').pop() || '';
            const dotIdx = lastSegment.lastIndexOf('.');
            if( dotIdx <= 0 ) return null;
            const ext = lastSegment.substring(dotIdx + 1).toLowerCase();
            return (ext.length > 0 && ext.length <= 10) ? ext : null;
        } catch(e) {
            return null;
        }
    }

    function applyCreatedFile(modalFrame, json) {
        insertDownloadLink(json.downloadUrl, json.title);

        const filesSection = jQuery('#tli-downloadable-files');
        if( filesSection.length && json.attachedFilesHtml ) {
            filesSection.replaceWith(json.attachedFilesHtml);
        }

        modalFrame.find('.tli-link-file-create-url-form, .tli-link-file-create-upload-form')
            .addClass('d-none')
            .each(function() { this.reset(); });
        modalFrame.find('.tli-link-file-create-error').empty();

        bootstrap.Modal.getInstance(modalFrame[0]).hide();
    }

    function performSearch(modalFrame) {

        const query = modalFrame.find('.tli-link-file-search-input').val().trim();

        const resultsContainer = modalFrame.find('.tli-link-file-results');
        resultsContainer.html(
            '<div class="d-flex justify-content-center p-3">' +
                '<i class="fa-solid fa-spinner fa-spin-pulse fa-2xl"></i>' +
            '</div>'
        );

        setModalControlsDisabled(modalFrame, true);
        if( modalFrame.attr('data-search-state') !== 'completed' ) {
            setSearchState(modalFrame, 'pending');
        }

        const onlyMine  = modalFrame.find('#tli-link-file-mine-only').is(':checked') ? '1' : '';
        const sort      = modalFrame.find('input[name="file-sort"]:checked').val() || '';

        const params = new URLSearchParams();
        if( onlyMine ) params.set('only-mine', '1');

        let endpoint;
        if( query.length >= 2 ) {
            endpoint = '/cerca/ajax/link-file/' + encodeURIComponent(query);
            if( sort === 'date' ) params.set('sort', 'date');
        } else {
            endpoint = '/cerca/ajax/link-file-latest';
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
            setSearchState(modalFrame, 'completed');
        });
    }


    let fileSearchDebounceTimer;

    function triggerSearchImmediate(modalFrame) {
        clearTimeout(fileSearchDebounceTimer);
        performSearch(modalFrame);
    }

    // Search button click
    jQuery(document).on('click', '.tli-link-file-search-btn', function() {
        triggerSearchImmediate( jQuery(this).closest('.modal') );
    });

    // Enter key in search input
    jQuery(document).on('keydown', '.tli-link-file-search-input', function(event) {
        if( event.key === 'Enter' ) {
            event.preventDefault();
            triggerSearchImmediate( jQuery(this).closest('.modal') );
        }
    });

    // Auto-search on filter change
    jQuery(document).on('change', '#tli-link-file-mine-only, #tli-link-file-modal input[name="file-sort"]', function() {
        triggerSearchImmediate( jQuery(this).closest('.modal') );
    });

    // Debounced auto-search 1s after the last keystroke
    jQuery(document).on('input', '.tli-link-file-search-input', function() {
        const modalFrame = jQuery(this).closest('.modal');
        updateRelevanceRadioState(modalFrame);
        clearTimeout(fileSearchDebounceTimer);
        fileSearchDebounceTimer = setTimeout(function() {
            performSearch(modalFrame).always(function() {
                modalFrame.find('.tli-link-file-search-input').trigger('focus');
            });
        }, 1000);
    });

    // Eager preload on page load, while the modal is still hidden
    const fileModalFrame = jQuery('#tli-link-file-modal');
    if( fileModalFrame.length ) {
        updateRelevanceRadioState(fileModalFrame);
        performSearch(fileModalFrame);
    }


    // "Crea collegamento" — insert selected text as link, or file name as link
    jQuery(document).on('click', '.tli-link-file-insert-link', function() {

        const resultRow     = jQuery(this).closest('.tli-link-file-result');
        const fileUrl       = resultRow.data('file-url');
        const fileTitle     = resultRow.data('file-title');
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
                // No selection — insert the file title as linked text,
                // inheriting any attributes active at the cursor (ins, bold, etc.)
                editor.model.change(writer => {
                    const selection = editor.model.document.selection;
                    const attrs = {};
                    for( const [key, value] of selection.getAttributes() ) {
                        attrs[key] = value;
                    }
                    attrs.linkHref = fileUrl;
                    const text = writer.createText(fileTitle, attrs);
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
        const fileTitle     = resultRow.data('file-title');
        const modalFrame    = jQuery('#tli-link-file-modal');

        insertDownloadLink(fileUrl, fileTitle);

        bootstrap.Modal.getInstance(modalFrame[0]).hide();
    });


    // "Crea via URL" / "Crea via Upload" — toggle the matching create form
    jQuery(document).on('click', '.tli-link-file-create-via-url', function() {
        const modalFrame = jQuery(this).closest('.modal');
        modalFrame.find('.tli-link-file-create-upload-form').addClass('d-none');
        modalFrame.find('.tli-link-file-create-error').empty();
        const urlForm = modalFrame.find('.tli-link-file-create-url-form');
        urlForm.toggleClass('d-none');
        if( !urlForm.hasClass('d-none') ) {
            urlForm.find('.tli-link-file-url-input').trigger('focus');
        }
    });

    jQuery(document).on('click', '.tli-link-file-create-via-upload', function() {
        const modalFrame = jQuery(this).closest('.modal');
        modalFrame.find('.tli-link-file-create-url-form').addClass('d-none');
        modalFrame.find('.tli-link-file-create-error').empty();
        const uploadForm = modalFrame.find('.tli-link-file-create-upload-form');
        uploadForm.toggleClass('d-none');
        if( !uploadForm.hasClass('d-none') ) {
            uploadForm.find('.tli-link-file-upload-input').trigger('focus');
        }
    });


    // Auto-fill format dropdown from URL extension as the user types
    jQuery(document).on('input', '.tli-link-file-url-input', function() {
        const ext = parseFormatFromUrl(this.value.trim());
        if( !ext ) return;
        const formatSelect = jQuery(this).closest('.tli-link-file-create-url-form').find('.tli-link-file-url-format');
        if( formatSelect.find('option[value="' + ext + '"]').length ) {
            formatSelect.val(ext);
        }
    });


    // URL form submit — create new external File and insert link
    jQuery(document).on('submit', '.tli-link-file-create-url-form', function(event) {
        event.preventDefault();
        const form          = jQuery(this);
        const modalFrame    = form.closest('.modal');
        const errorBox      = form.find('.tli-link-file-create-error');
        const submitBtn     = form.find('.tli-link-file-url-submit');

        errorBox.empty();
        submitBtn.prop('disabled', true);

        jQuery.ajax({
            url: modalFrame.data('createFromUrlUrl'),
            method: 'POST',
            data: {
                url:    form.find('.tli-link-file-url-input').val().trim(),
                title:  form.find('.tli-link-file-url-title').val().trim(),
                format: form.find('.tli-link-file-url-format').val()
            },
            dataType: 'json'
        }).done(function(json) {
            applyCreatedFile(modalFrame, json);
        }).fail(function(jqXHR) {
            errorBox.html('<p class="alert alert-danger">' + (jqXHR.responseText || 'Errore durante la creazione del file') + '</p>');
        }).always(function() {
            submitBtn.prop('disabled', false);
        });
    });


    // Upload form submit — upload new local File and insert link
    jQuery(document).on('submit', '.tli-link-file-create-upload-form', function(event) {
        event.preventDefault();
        const form          = jQuery(this);
        const modalFrame    = form.closest('.modal');
        const errorBox      = form.find('.tli-link-file-create-error');
        const submitBtn     = form.find('.tli-link-file-upload-submit');
        const fileInput     = form.find('.tli-link-file-upload-input')[0];
        const title         = form.find('.tli-link-file-upload-title').val().trim();

        errorBox.empty();

        if( !fileInput.files || fileInput.files.length === 0 ) {
            errorBox.html('<p class="alert alert-danger">Seleziona un file da caricare.</p>');
            return;
        }

        if( title === '' ) {
            errorBox.html('<p class="alert alert-danger">Il titolo è obbligatorio.</p>');
            return;
        }

        const formData = new FormData();
        formData.append('files[]', fileInput.files[0]);
        formData.append('title', title);

        submitBtn.prop('disabled', true);

        jQuery.ajax({
            url: modalFrame.data('createFromUploadUrl'),
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json'
        }).done(function(json) {
            applyCreatedFile(modalFrame, json);
        }).fail(function(jqXHR) {
            errorBox.html('<p class="alert alert-danger">' + (jqXHR.responseText || 'Errore durante il caricamento') + '</p>');
        }).always(function() {
            submitBtn.prop('disabled', false);
        });
    });


    // Reset create forms when the modal is hidden
    jQuery(document).on('hidden.bs.modal', '#tli-link-file-modal', function() {
        const modalFrame = jQuery(this);
        modalFrame.find('.tli-link-file-create-url-form, .tli-link-file-create-upload-form')
            .addClass('d-none')
            .each(function() { this.reset(); });
        modalFrame.find('.tli-link-file-create-error').empty();
    });
});
