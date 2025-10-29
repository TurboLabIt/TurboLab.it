//import $ from 'jquery';


$(document).on('click', '.tli-file-upload',  function(event) {
    $(this).siblings('input[type="file"]').click();
});


$(document).on('change', 'input[type="file"].tli-file-uploader', function() {

    const files = this.files;
    if (files.length === 0) {
        return;
    }

    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }

    let thisInputFile = $(this);

    /*
    let editorFileGallery = $('#tli-article-editor-file-gallery');

    const uploadCommandContainer = $('.tli-file-upload');
    uploadCommandContainer.addClass('d-none');

    const progressBarContainer = $('#tli-file-upload-progress');
    progressBarContainer.removeClass('collapse');

    const progressBar = progressBarContainer.find('.progress-bar');
    progressBar.addClass('progress-bar-animated progress-bar-striped');
    progressBar.removeClass('bg-success bg-danger');
    progressBar.width('0%');
    progressBar.attr('aria-valuenow', 0).text('0%');

    let errorMessage = editorFileGallery.find('.alert-danger');
    errorMessage.addClass('collapse');

    editorFileGallery.find('.border-success').removeClass('border border-2 border-success');
    */
debugger;
    $.ajax({
        //url: editorFileGallery.data('save-url'),
        url: thisInputFile.data('save-url'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        /*xhr: function() {
            const xhr = new window.XMLHttpRequest();
            // Upload progress
            xhr.upload.addEventListener('progress', function(evt) {
                if (evt.lengthComputable) {
                    const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                    progressBar.width(percentComplete + '%');
                    progressBar.attr('aria-valuenow', percentComplete).text(percentComplete + '%');
                }
            }, false);
            return xhr;
        },*/
        success: function(response) {

            /*progressBar.addClass('bg-success');

            $('#tli-files-gallery').append(response);

            const container = $('#tli-article-editor-file-gallery');
            const target = container.find('.border-success');

            if(target.length) {
                container.animate({
                    scrollTop: target.offset().top - container.offset().top + container.scrollTop()
                }, 1000);
            }

            editorFileGallery.find('.tli-no-files-guide').fadeOut('slow', function(){
                editorFileGallery.find('.tli-files-guide').fadeIn('fast');
            });*/
            let target = $('#tli-downloadable-files');
            target.fadeOut('slow', function() {
                target
                    .html(response)
                    .fadeIn();
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {

            /*progressBar.addClass('bg-danger');

            errorMessage
                .removeClass('collapse')
                .html(jqXHR.responseText);*/
            alert(jqXHR.responseText);
        },
        complete: function() {

            thisInputFile.val('');
            /*progressBar.removeClass('progress-bar-animated progress-bar-striped');

            progressBarContainer.delay(3000).fadeOut('slow', function(){

                $(this).addClass('collapse');
                $(this).css('display', '');
                uploadCommandContainer.removeClass('d-none');
            });*/
        }
    });
});


/**
 * FILE INSERT LOGIC
 * ------------------
 * **TODO** --moved to assets/js/article-edit-ckeditor.js--
 */


$(document).on('click', '#tli-files-gallery .tli-delete-file', function(e) {

    e.preventDefault();
    if( !confirm('Sicuro?') ) {
        return false;
    }

    $(this).closest('.col').fadeOut();

    let galleryItemContainer = $(this).closest('.col');
    let deleteUrl = galleryItemContainer.find('[data-delete-url]').data('delete-url');

    $.ajax({
        url: deleteUrl,
        type: 'DELETE',
        error: function(jqXHR, textStatus, errorThrown) {

            let errorMessage = jqXHR.responseText ?? null;
            if( errorMessage && errorMessage != '' ) {

                alert(errorMessage);
                return false;
            }
        }
    });
});
