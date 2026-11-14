//import $ from 'jquery';
import Validator from './validator';
import { URL_REGEX, isFileDrag, guessFormatFromUrl, sizeLabel } from './download-source';


// a submit (not a click on the button) also catches Enter in a field, which would otherwise post the form natively
$(document).on('submit', '#tli-edit-file', function(event) {

    event.preventDefault();

    let form = $(this);

    const formUrl = form.attr('action');
    if( !Validator.isSameOriginHttpsUrl(formUrl) ) {
        return;
    }

    let errorBox    = form.find('.tli-edit-file-error').addClass('d-none').empty();
    let submitBtn   = $('.tli-file-button-ok').prop('disabled', true);

    $.ajax({
        url: formUrl,
        type: form.attr('method'),
        data: form.serialize(),
        success: function(response) {

            $('#tli-ajax-modal').find('.btn-close').trigger('click');

            let target = $('#tli-downloadable-files');
            target.fadeOut('slow', function() {
                target
                    // response is trusted server-rendered HTML for the downloadable-files
                    // section; the Validator guard ensures it came from our origin.
                    .html(response)
                    .fadeIn();
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {

            errorBox
                .text(jqXHR.responseText || 'Errore durante il salvataggio')
                .removeClass('d-none');
        },
        complete: function() {

            submitBtn.prop('disabled', false);
        }
    });
});


$(document).on('click', '#tli-downloadable-files .tli-delete-file', function() {

    let fileRow     = $(this).closest('.tli-file-download');
    const linksNum  = countFileLinksInArticleBody( fileRow.data('file-url') );

    fileRow.find('.tli-file-delete-confirm-links')
        .toggleClass('d-none', linksNum == 0)
        .find('.tli-file-links-num-text')
        .text(
            linksNum == 1
                ? "Il testo dell'articolo contiene ancora un link a questo file: ricordati di rimuoverlo,"
                : "Il testo dell'articolo contiene ancora " + linksNum + " link a questo file: ricordati di rimuoverli,"
        );

    fileRow.find('.tli-file-delete-error').addClass('d-none').empty();

    fileRow
        .addClass('tli-file-confirming')
        .find('.tli-file-delete-confirm').removeClass('d-none')
        .find('.tli-file-delete-cancel').trigger('focus');
});


$(document).on('click', '#tli-downloadable-files .tli-file-delete-cancel', function() {
    closeDeleteConfirm( $(this).closest('.tli-file-download') );
});


$(document).on('keydown', '#tli-downloadable-files .tli-file-delete-confirm', function(event) {

    if( event.key === 'Escape' ) {
        closeDeleteConfirm( $(this).closest('.tli-file-download') );
    }
});


$(document).on('click', '#tli-downloadable-files .tli-file-delete-ok', function() {

    let fileRow     = $(this).closest('.tli-file-download');
    let buttons     = fileRow.find('.tli-file-delete-confirm button').prop('disabled', true);
    let errorBox    = fileRow.find('.tli-file-delete-error').addClass('d-none').empty();

    $.ajax({
        url: fileRow.data('detach-from-article-url'),
        type: 'DELETE',
        success: function() {

            fileRow.slideUp(function() {
                fileRow.remove();
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {

            errorBox
                .text(jqXHR.responseText || "Errore durante l'eliminazione")
                .removeClass('d-none');

            buttons.prop('disabled', false);
        }
    });
});


function closeDeleteConfirm(fileRow)
{
    fileRow
        .removeClass('tli-file-confirming')
        .find('.tli-file-delete-confirm').addClass('d-none');

    fileRow.find('.tli-delete-file').trigger('focus');
}


/**
 * Saving the article re-attaches every file its body links to, and a deleted file leaves a dead link behind:
 * the delete confirmation warns about both. Counted on the live editor data, unsaved changes included
 */
function countFileLinksInArticleBody(fileUrl)
{
    const editable = document.querySelector('.ck-editor__editable');
    if( !editable || !editable.ckeditorInstance || !fileUrl ) {
        return 0;
    }

    const filePath  = new URL(fileUrl, location.href).pathname;
    const body      = new DOMParser().parseFromString(editable.ckeditorInstance.getData(), 'text/html');

    return [...body.querySelectorAll('a[href]')].filter(function(link) {

        try {
            const linkUrl = new URL(link.getAttribute('href'), location.href);
            return linkUrl.host == location.host && linkUrl.pathname == filePath;

        } catch(e) {
            return false;
        }

    }).length;
}


//<editor-fold defaultstate="collapsed" desc="*** Sostituisci: a new version of the Download, same ID ***">
// the new source, file or URL, of the modal currently open
let replaceSource = null;


function replaceBox() {
    return $('#tli-ajax-modal .tli-file-replace');
}


function setReplaceSource(source) {

    replaceSource = source;

    const box = replaceBox();
    box.find('.tli-file-replace-error').addClass('d-none').empty();

    if( !source ) {
        box.find('.tli-file-replace-confirm').addClass('d-none');
        return;
    }

    box.find('.tli-file-replace-source-name').text(
        source.type == 'file' ? source.file.name + ' (' + sizeLabel(source.file.size) + ')' : source.url
    );

    box.find('.tli-file-replace-confirm').removeClass('d-none')
        .find('.tli-file-replace-cancel').trigger('focus');

    // from a URL the format can't be its extension: the system guesses, the author corrects (in the form above)
    if( source.type == 'url' ) {

        const select = $('#tli-file-format');
        select.find('option.external-only').removeClass('d-none');

        const format = guessFormatFromUrl(source.url);
        if( format && select.find('option[value="' + format + '"]').length ) {
            select.val(format);
        }
    }
}


$(document).on('click', '#tli-ajax-modal .tli-file-replace-pick', function() {
    replaceBox().find('.tli-file-replace-input').val('').trigger('click');
});

$(document).on('change', '#tli-ajax-modal .tli-file-replace-input', function() {

    if( this.files && this.files[0] ) {
        replaceBox().find('.tli-file-replace-url').val('');
        setReplaceSource({ type: 'file', file: this.files[0] });
    }
});

$(document).on('input', '#tli-ajax-modal .tli-file-replace-url', function() {

    const url = this.value.trim();
    setReplaceSource( URL_REGEX.test(url) ? { type: 'url', url: url } : null );
});

$(document).on('dragenter dragover', '#tli-ajax-modal .tli-file-replace', function(event) {

    if( isFileDrag(event) ) {
        event.preventDefault();
        event.originalEvent.dataTransfer.dropEffect = 'copy';
        $(this).addClass('tli-file-add-dragover');
    }
});

$(document).on('dragleave', '#tli-ajax-modal .tli-file-replace', function(event) {
    if( !this.contains(event.relatedTarget) ) {
        $(this).removeClass('tli-file-add-dragover');
    }
});

$(document).on('drop', '#tli-ajax-modal .tli-file-replace', function(event) {

    $(this).removeClass('tli-file-add-dragover');
    if( !isFileDrag(event) ) {
        return;
    }

    event.preventDefault();

    // one new version: any other dropped file is ignored
    const file = event.originalEvent.dataTransfer.files[0];
    if( file ) {
        $(this).find('.tli-file-replace-url').val('');
        setReplaceSource({ type: 'file', file: file });
    }
});

$(document).on('click', '#tli-ajax-modal .tli-file-replace-cancel', function() {

    const box = replaceBox();
    box.find('.tli-file-replace-url, .tli-file-replace-input').val('');
    setReplaceSource(null);
    box.find('.tli-file-replace-url').trigger('focus');
});


$(document).on('click', '#tli-ajax-modal .tli-file-replace-ok', function() {

    const box       = replaceBox();
    const replaceUrl= box.data('replace-url');
    if( !replaceSource || !Validator.isSameOriginHttpsUrl(replaceUrl) ) {
        return;
    }

    const buttons   = box.find('.tli-file-replace-confirm button').prop('disabled', true);
    const errorBox  = box.find('.tli-file-replace-error').addClass('d-none').empty();

    const data = new FormData();
    if( replaceSource.type == 'file' ) {
        data.append('files[]', replaceSource.file);
    } else {
        data.append('url', replaceSource.url);
        data.append('format', $('#tli-file-format').val() || '');
    }

    $.ajax({
        url: replaceUrl, method: 'POST', data: data,
        contentType: false, processData: false, dataType: 'json'
    })
        .done(function(json) {

            $('#tli-ajax-modal').find('.btn-close').trigger('click');

            if( json.attachedFilesHtml ) {
                $('#tli-downloadable-files').html(json.attachedFilesHtml);
            }
        })
        .fail(function(jqXHR) {

            let message = 'Errore durante la sostituzione.';
            if( jqXHR.status == 413 ) {
                message = 'Il file è troppo grande.';
            } else if( ( jqXHR.getResponseHeader('Content-Type') || '' ).startsWith('text/plain') && jqXHR.responseText ) {
                message = jqXHR.responseText;
            }

            errorBox.text(message).removeClass('d-none');
        })
        .always(() => buttons.prop('disabled', false));
});


$(document).on('hidden.bs.modal', '#tli-ajax-modal', () => replaceSource = null);
//</editor-fold>
