import { Plugin, ButtonView } from 'ckeditor5';
import { URL_REGEX, isFileDrag, titleFromFileName, titleFromUrl, guessFormatFromUrl, md5OfFile, sizeLabel } from '../download-source';


export default class TliLinkFile extends Plugin {
    static get pluginName() { return 'TliLinkFile'; }

    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('tliLinkFile', locale => {
            const view = new ButtonView(locale);
            view.set({
                icon: $('#tli-toolbar-icons .tli-link-file-icon')[0].outerHTML,
                tooltip: 'Aggiungi un Download (Ctrl+D)',
                withText: false
            });

            view.on('execute', () => openLinkFileModal(editor));

            return view;
        });
    }
}


/**
 * "Aggiungi un Download" (docs/downloadable-files.md): one field takes a search, a URL or a file (picked or dropped).
 * A URL or a file is first looked up among the existing Downloads (same URL, same content); only when there's
 * no match the author can create a new one, or replace a similar one with this new version
 */
function openLinkFileModal(editor, droppedFile = null) {

    // Capture the selected text BEFORE opening the modal (focus will shift)
    let selectedText = '';
    if( editor ) {
        const selection = editor.model.document.selection;
        for (const range of selection.getRanges()) {
            for (const item of range.getItems()) {
                if (item.is('$text') || item.is('$textProxy')) {
                    selectedText += item.data;
                }
            }
        }
    }

    const modalFrame = jQuery('#tli-link-file-modal');
    modalFrame.data('selected-text', selectedText.trim());

    bootstrap.Modal.getOrCreateInstance(modalFrame[0]).show();

    if( droppedFile ) {
        modalFrame.trigger('tli:file-source', [droppedFile]);
    }

    setTimeout(() => {
        modalFrame.find(droppedFile ? '.tli-link-file-create-title' : '.tli-link-file-search-input').trigger('focus');
    }, 500);
}


function currentEditor() {
    const editable = document.querySelector('.ck-editor__editable');
    return editable ? editable.ckeditorInstance : null;
}


jQuery(function() {

    const modalFrame = jQuery('#tli-link-file-modal');
    if( !modalFrame.length ) {
        return;
    }

    // the source being added: { type: 'file', file, hash } or { type: 'url', url }. "seq" discards stale lookups
    let source  = null;
    let seq     = 0;


    // "Aggiungi un Download" box: a click, or a file dropped on it, opens the modal
    jQuery(document).on('click', '.tli-file-add', function() {
        openLinkFileModal( currentEditor() );
    });

    jQuery(document).on('dragenter dragover', '.tli-file-add, #tli-link-file-modal .tli-link-file-dropzone', function(event) {

        if( !isFileDrag(event) ) {
            return;
        }

        event.preventDefault();
        event.originalEvent.dataTransfer.dropEffect = 'copy';
        jQuery(this).addClass('tli-file-add-dragover');
    });

    jQuery(document).on('dragleave', '.tli-file-add, #tli-link-file-modal .tli-link-file-dropzone', function(event) {
        // leaving for a child element is not leaving
        if( !this.contains(event.relatedTarget) ) {
            jQuery(this).removeClass('tli-file-add-dragover');
        }
    });

    jQuery(document).on('drop', '.tli-file-add, #tli-link-file-modal .tli-link-file-dropzone', function(event) {

        jQuery(this).removeClass('tli-file-add-dragover');
        if( !isFileDrag(event) ) {
            return;
        }

        event.preventDefault();

        // one Download at a time: any other dropped file is ignored
        const file = event.originalEvent.dataTransfer.files[0];
        if( !file ) {
            return;
        }

        if( jQuery(this).hasClass('tli-file-add') ) {
            openLinkFileModal(currentEditor(), file);
        } else {
            setFileSource(file);
        }
    });

    // a file dropped anywhere else must not make the browser open it, leaving the unsaved article behind
    window.addEventListener('dragover', function(event) {

        if( !event.defaultPrevented && isFileDrag(event) ) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'none';
        }
    });

    modalFrame.on('tli:file-source', (event, file) => setFileSource(file));


    //<editor-fold defaultstate="collapsed" desc="*** source: file or URL ***">
    function clearSource() {

        seq++;
        source = null;

        modalFrame.find('.tli-link-file-source, .tli-link-file-matches, .tli-link-file-similar, .tli-link-file-create-form')
            .addClass('d-none');
        modalFrame.find('.tli-link-file-matches-list, .tli-link-file-similar-list, .tli-link-file-create-error').empty();
        modalFrame.find('.tli-link-file-create-form')[0].reset();
        modalFrame.find('.tli-link-file-search-box').removeClass('d-none');
    }


    function showSource(icon, name, status) {
        modalFrame.find('.tli-link-file-search-box').addClass('d-none');
        modalFrame.find('.tli-link-file-source').removeClass('d-none');
        modalFrame.find('.tli-link-file-source-icon').attr('class', 'fa-solid ' + icon + ' tli-link-file-source-icon');
        modalFrame.find('.tli-link-file-source-name').text(name);
        modalFrame.find('.tli-link-file-source-status').text(status);
    }


    async function setFileSource(file) {

        clearSource();
        modalFrame.find('.tli-link-file-search-input').val('');

        if( file.size === 0 ) {
            showSource('fa-triangle-exclamation', file.name, 'file vuoto o cartella: non si può caricare.');
            return;
        }

        const mySeq = seq;
        source = { type: 'file', file: file };
        showSource('fa-file-arrow-up', file.name, '(' + sizeLabel(file.size) + ') verifico se è già su TurboLab.it...');

        let hash;
        try {
            hash = await md5OfFile(file, percent => {
                if( mySeq === seq ) {
                    modalFrame.find('.tli-link-file-source-status')
                        .text('(' + sizeLabel(file.size) + ') verifico se è già su TurboLab.it... ' + percent + '%');
                }
            });

        } catch(e) {
            hash = null;
        }

        if( mySeq !== seq ) {
            return;
        }

        source.hash = hash;
        lookup(mySeq, hash ? { hash: hash, name: titleFromFileName(file.name) } : null);
    }


    function setUrlSource(url) {

        clearSource();

        const mySeq = seq;
        source = { type: 'url', url: url };
        showSource('fa-link', url, 'verifico se è già su TurboLab.it...');

        lookup(mySeq, { url: url, name: titleFromUrl(url) });
    }


    function lookup(mySeq, params) {

        // a file that couldn't be read: no check, straight to the form (the server still dedupes by content)
        if( !params ) {
            showCreateForm();
            return;
        }

        jQuery.getJSON(modalFrame.data('lookupUrl') + '&' + jQuery.param(params))
            .done(function(json) {

                if( mySeq !== seq ) {
                    return;
                }

                modalFrame.find('.tli-link-file-source-status').text('');

                if( json.matchesNum > 0 ) {

                    modalFrame.find('.tli-link-file-matches-text').text(
                        source.type == 'file' ? 'Questo file è già su TurboLab.it.' : 'Questo URL è già su TurboLab.it.'
                    );
                    modalFrame.find('.tli-link-file-matches-list').html(json.matchesHtml);
                    modalFrame.find('.tli-link-file-matches').removeClass('d-none');
                    return;
                }

                if( json.similarNum > 0 ) {
                    modalFrame.find('.tli-link-file-similar-list').html(json.similarHtml);
                    modalFrame.find('.tli-link-file-similar').removeClass('d-none');
                }

                showCreateForm();
            })
            .fail(function(jqXHR) {

                if( mySeq === seq ) {
                    modalFrame.find('.tli-link-file-source-status').text(jqXHR.responseText || 'verifica non riuscita');
                }
            });
    }


    function showCreateForm() {

        const form      = modalFrame.find('.tli-link-file-create-form').removeClass('d-none');
        const isFile    = source.type == 'file';

        form.find('.tli-link-file-create-title-label').text(
            isFile ? 'Titolo (sarà anche il nome del file scaricato)' : 'Titolo (nome leggibile)'
        );
        form.find('.tli-link-file-create-title').val( isFile ? titleFromFileName(source.file.name) : titleFromUrl(source.url) );

        // a local file's format is its extension, set by the server
        form.find('.tli-link-file-create-format-box').toggleClass('d-none', isFile);
        form.find('.tli-link-file-create-format').prop('required', !isFile);

        if( !isFile ) {
            const format = guessFormatFromUrl(source.url);
            const select = form.find('.tli-link-file-create-format');
            if( format && select.find('option[value="' + format + '"]').length ) {
                select.val(format);
            }
        }
    }


    modalFrame.on('click', '.tli-link-file-source-clear', function() {
        clearSource();
        modalFrame.find('.tli-link-file-search-input').val('').trigger('focus');
        performSearch();
    });

    modalFrame.on('click', '.tli-link-file-pick', function() {
        modalFrame.find('.tli-link-file-upload-input').val('').trigger('click');
    });

    modalFrame.on('change', '.tli-link-file-upload-input', function() {
        if( this.files && this.files[0] ) {
            setFileSource(this.files[0]);
        }
    });
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** create, replace ***">
    modalFrame.on('submit', '.tli-link-file-create-form', function(event) {

        event.preventDefault();

        const form      = jQuery(this);
        const errorBox  = form.find('.tli-link-file-create-error').empty();
        const submitBtn = form.find('.tli-link-file-create-submit').prop('disabled', true);
        const title     = form.find('.tli-link-file-create-title').val().trim();

        let request;
        if( source.type == 'file' ) {

            const formData = new FormData();
            formData.append('files[]', source.file);
            formData.append('title', title);

            request = jQuery.ajax({
                url: modalFrame.data('createFromUploadUrl'), method: 'POST', data: formData,
                contentType: false, processData: false, dataType: 'json'
            });

        } else {

            request = jQuery.ajax({
                url: modalFrame.data('createFromUrlUrl'), method: 'POST', dataType: 'json',
                data: { url: source.url, title: title, format: form.find('.tli-link-file-create-format').val() }
            });
        }

        request
            .done(json => applyCreatedFile(json))
            .fail(jqXHR => errorBox.html( errorAlert( requestErrorMessage(jqXHR) ) ))
            .always(() => submitBtn.prop('disabled', false));
    });


    modalFrame.on('click', '.tli-link-file-replace', function() {
        modalFrame.find('.tli-link-file-replace-confirm').addClass('d-none');
        jQuery(this).closest('.tli-link-file-result').find('.tli-link-file-replace-confirm').removeClass('d-none')
            .find('.tli-link-file-replace-cancel').trigger('focus');
    });

    modalFrame.on('click', '.tli-link-file-replace-cancel', function() {
        jQuery(this).closest('.tli-link-file-replace-confirm').addClass('d-none');
    });

    modalFrame.on('click', '.tli-link-file-replace-ok', function() {

        const confirmBox    = jQuery(this).closest('.tli-link-file-replace-confirm');
        const buttons       = confirmBox.find('button').prop('disabled', true);
        const errorBox      = confirmBox.find('.tli-link-file-replace-error').empty();

        let data;
        if( source.type == 'file' ) {
            data = new FormData();
            data.append('files[]', source.file);
        } else {
            data = new FormData();
            data.append('url', source.url);
        }

        jQuery.ajax({
            url: confirmBox.data('replaceUrl'), method: 'POST', data: data,
            contentType: false, processData: false, dataType: 'json'
        })
            .done(json => applyCreatedFile(json))
            .fail(jqXHR => errorBox.text( requestErrorMessage(jqXHR) ))
            .always(() => buttons.prop('disabled', false));
    });


    function applyCreatedFile(json) {

        insertDownloadLink(json.downloadUrl, json.title);

        const filesSection = jQuery('#tli-downloadable-files');
        if( filesSection.length && json.attachedFilesHtml ) {
            filesSection.html(json.attachedFilesHtml);
        }

        bootstrap.Modal.getInstance(modalFrame[0]).hide();
    }


    // the controller answers in plain text; anything else (nginx 413, a gateway error...) is not for the author's eyes
    function requestErrorMessage(jqXHR) {

        if( jqXHR.status == 413 ) {
            return 'Il file è troppo grande.';
        }

        const isPlainText = ( jqXHR.getResponseHeader('Content-Type') || '' ).startsWith('text/plain');
        return isPlainText && jqXHR.responseText ? jqXHR.responseText : 'Errore durante il salvataggio.';
    }


    function errorAlert(text) {
        return jQuery('<p class="alert alert-danger"></p>').text(text);
    }
    //</editor-fold>


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

            // A line of its own: an empty paragraph gets filled, otherwise it goes before/after the one with
            // the cursor. Never merged into its text, nor replacing a selection: from the box below the
            // downloadable files the cursor is wherever it was left, often the start of the body
            const insertedRange = editor.model.insertContent(paragraph, editor.model.schema.findOptimalInsertionRange(selection));

            // keep typing after the link
            const block = insertedRange.end.parent.is('rootElement') ? insertedRange.end.nodeBefore : insertedRange.end.parent;
            writer.setSelection(block, 'end');
        });

        editor.editing.view.focus();
    }


    //<editor-fold defaultstate="collapsed" desc="*** search ***">
    function setModalControlsDisabled(disabled) {
        modalFrame.find('#tli-link-file-mine-only, input[name="file-sort"]').prop('disabled', disabled);
    }

    function updateRelevanceRadioState() {
        const query = modalFrame.find('.tli-link-file-search-input').val().trim();
        const relevanceRadio = modalFrame.find('#tli-link-file-sort-relevance');
        relevanceRadio.prop('disabled', query === '');
        if( query === '' && relevanceRadio.is(':checked') ) {
            modalFrame.find('#tli-link-file-sort-date').prop('checked', true);
        }
    }

    function performSearch() {

        const query = modalFrame.find('.tli-link-file-search-input').val().trim();

        const resultsContainer = modalFrame.find('.tli-link-file-results');
        resultsContainer.html(
            '<div class="d-flex justify-content-center p-3">' +
                '<i class="fa-solid fa-spinner fa-spin-pulse fa-2xl"></i>' +
            '</div>'
        );

        setModalControlsDisabled(true);

        const params = new URLSearchParams();
        if( modalFrame.find('#tli-link-file-mine-only').is(':checked') ) {
            params.set('only-mine', '1');
        }

        let endpoint;
        if( query.length >= 2 ) {
            endpoint = '/cerca/ajax/link-file/' + encodeURIComponent(query);
            if( modalFrame.find('input[name="file-sort"]:checked').val() === 'date' ) {
                params.set('sort', 'date');
            }
        } else {
            endpoint = '/cerca/ajax/link-file-latest';
        }

        const qs = params.toString();
        if( qs ) endpoint += '?' + qs;

        abortSearch();
        const request = searchRequest = jQuery.get(endpoint);

        request.done(function(html) {
            resultsContainer.html(html);
        }).fail(function(jqXHR, textStatus) {
            if( textStatus !== 'abort' ) {
                resultsContainer.html( errorAlert(jqXHR.responseText || 'Errore durante la ricerca') );
            }
        }).always(function() {
            // a newer search owns the controls now
            if( request === searchRequest ) {
                searchRequest = null;
                setModalControlsDisabled(false);
                updateRelevanceRadioState();
            }
        });
    }


    // the author typed again: the running search answers a query that's gone
    function abortSearch() {
        if( searchRequest ) {
            const request = searchRequest;
            searchRequest = null;
            request.abort();
            setModalControlsDisabled(false);
        }
    }


    let inputDebounceTimer;
    let searchRequest = null;

    // Enter: search (or look the URL up) right away
    modalFrame.on('keydown', '.tli-link-file-search-input', function(event) {
        if( event.key === 'Enter' ) {
            event.preventDefault();
            clearTimeout(inputDebounceTimer);
            handleInput();
        }
    });

    modalFrame.on('change', '#tli-link-file-mine-only, input[name="file-sort"]', () => performSearch());

    // a URL is looked up, anything else searched, shortly after the last keystroke
    modalFrame.on('input', '.tli-link-file-search-input', function() {
        abortSearch();
        updateRelevanceRadioState();
        clearTimeout(inputDebounceTimer);
        inputDebounceTimer = setTimeout(handleInput, URL_REGEX.test( this.value.trim() ) ? 400 : 800);
    });

    function handleInput() {

        const value = modalFrame.find('.tli-link-file-search-input').val().trim();

        if( URL_REGEX.test(value) ) {

            if( source?.type != 'url' || source.url != value ) {
                setUrlSource(value);
            }

            return;
        }

        if( source ) {
            clearSource();
        }

        performSearch();
    }

    // the latest Downloads, preloaded while the modal is still hidden
    updateRelevanceRadioState();
    performSearch();
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** use an existing Download ***">
    // "Usa come link sul testo": the selected text becomes the link, or the Download title is inserted as one
    modalFrame.on('click', '.tli-link-file-insert-link', function() {

        const resultRow     = jQuery(this).closest('.tli-link-file-result');
        const fileUrl       = resultRow.data('file-url');
        const fileTitle     = resultRow.data('file-title');
        const selectedText  = modalFrame.data('selected-text') || '';
        const editor        = currentEditor();

        if( editor ) {

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


    // "Usa come riga » Download:"
    modalFrame.on('click', '.tli-link-file-insert-download', function() {

        const resultRow = jQuery(this).closest('.tli-link-file-result');
        insertDownloadLink(resultRow.data('file-url'), resultRow.data('file-title'));

        bootstrap.Modal.getInstance(modalFrame[0]).hide();
    });
    //</editor-fold>


    modalFrame.on('hidden.bs.modal', function() {
        clearSource();
        modalFrame.find('.tli-link-file-search-input').val('');
        modalFrame.find('.tli-link-file-dropzone').removeClass('tli-file-add-dragover');
    });
});
