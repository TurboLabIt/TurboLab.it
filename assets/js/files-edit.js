//import $ from 'jquery';
import Validator from './validator';


$(document).on('click', '.tli-file-upload',  function(event) {
    $(this).siblings('input[type="file"]').click();
});


$(document).on('change', 'input[type="file"].tli-file-uploader', function() {

    const files = this.files;
    if (files.length === 0) {
        return;
    }

    let thisInputFile = $(this);

    const saveUrl = thisInputFile.data('save-url');
    if( !Validator.isSameOriginHttpsUrl(saveUrl) ) {
        return;
    }

    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }

    $.ajax({
        url: saveUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {

            let target = $('#tli-downloadable-files');
            target.fadeOut('slow', function() {
                target
                    // response is trusted server-rendered HTML for the downloadable-files
                    // section; the Validator guard ensures it came from our origin.
                    .html(response)
                    .fadeIn('slow', function() {
                        target.get(0).scrollIntoView({behavior: 'smooth'});
                    });
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {

            alert(jqXHR.responseText);
        },
        complete: function() {

            thisInputFile.val('');
        }
    });
});


$(document).on('click', '.tli-file-button-ok',  function(event) {

    event.preventDefault();

    let form = $('#tli-edit-file');

    const formUrl = form.attr('action');
    if( !Validator.isSameOriginHttpsUrl(formUrl) ) {
        return;
    }

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

            let errorMessage = jqXHR.responseText ?? null;
            if( errorMessage && errorMessage != '' ) {

                alert(errorMessage);
                return false;
            }
        }
    });
});


$(document).on('click', '#tli-downloadable-files .tli-delete-file', function(e) {

    e.preventDefault();
    if( !confirm('Sei sicuro di voler eliminare questo file?') ) {
        return false;
    }

    let fileContainer = $(this).closest('.tli-file-download');
    fileContainer
        .removeClass('d-flex')
        .fadeOut();

    let deleteUrl = $(this).closest('[data-detach-from-article-url]').data('detach-from-article-url');

    $.ajax({
        url: deleteUrl,
        type: 'DELETE',
        error: function(jqXHR, textStatus, errorThrown) {

            fileContainer.fadeIn(function(){
                fileContainer.addClass('d-flex');
            });

            let errorMessage = jqXHR.responseText ?? null;
            if( errorMessage && errorMessage != '' ) {

                alert(errorMessage);
                return false;
            }
        }
    });
});
