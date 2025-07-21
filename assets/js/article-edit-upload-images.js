//import $ from 'jquery';


jQuery(document).on('click', '.tli-image-upload',  function(event) {
    $(this).siblings('input[type="file"]').click();
});


$(document).on('change', '#tli-article-editor-image-gallery input[type="file"]', function() {

    const files = this.files;
    if (files.length === 0) {
        return;
    }

    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('images[]', files[i]);
    }

    const uploadCommandContainer = $('.tli-image-upload');
    uploadCommandContainer.addClass('d-none');

    const progressBarContainer = $('#tli-image-upload-progress');
    progressBarContainer.removeClass('collapse');

    const progressBar = progressBarContainer.find('.progress-bar');
    progressBar.addClass('progress-bar-animated progress-bar-striped');
    progressBar.removeClass('bg-success bg-danger');
    progressBar.width('0%');
    progressBar.attr('aria-valuenow', 0).text('0%');

    $.ajax({
        url: $('#tli-article-editor-image-gallery').data('save-url'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
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
        },
        success: function(response) {
            debugger;
            progressBar.addClass('bg-success');
            //progressBarContainer.append('<div class="alert alert-success mt-2">✅ ' + response.message + '</div>');

            $('#tli-article-editor-image-gallery .tli-no-images-guide').fadeOut('slow', function(){
                $('#tli-article-editor-image-gallery .tli-images-guide').fadeIn('fast');
            });

        },
        error: function(jqXHR, textStatus, errorThrown) {
            debugger;
            progressBar.addClass('bg-danger');
            //const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.error : 'An unknown error occurred.';
            //progressBarContainer.append('<div class="alert alert-danger mt-2">❌ ' + errorMsg + '</div>');
        },
        complete: function() {

            $(this).val('');
            progressBar.removeClass('progress-bar-animated progress-bar-striped');

            progressBarContainer.delay(3000).fadeOut('slow', function(){

                $(this).addClass('collapse');
                $(this).css('display', '');
                uploadCommandContainer.removeClass('d-none');
            });
        }
    });
});
