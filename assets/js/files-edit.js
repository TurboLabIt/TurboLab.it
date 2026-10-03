//import $ from 'jquery';
import Validator from './validator';


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
