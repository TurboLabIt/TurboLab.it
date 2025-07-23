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

    let thisInputFile = $(this);

    let editorImageGallery = $('#tli-article-editor-image-gallery');

    const uploadCommandContainer = $('.tli-image-upload');
    uploadCommandContainer.addClass('d-none');

    const progressBarContainer = $('#tli-image-upload-progress');
    progressBarContainer.removeClass('collapse');

    const progressBar = progressBarContainer.find('.progress-bar');
    progressBar.addClass('progress-bar-animated progress-bar-striped');
    progressBar.removeClass('bg-success bg-danger');
    progressBar.width('0%');
    progressBar.attr('aria-valuenow', 0).text('0%');

    let errorMessage = editorImageGallery.find('.alert-danger');
    errorMessage.addClass('collapse');

    editorImageGallery.find('.border-success').removeClass('border border-2 border-success')

    $.ajax({
        url: editorImageGallery.data('save-url'),
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

            progressBar.addClass('bg-success');

            $('#tli-images-gallery').append(response);

            const container = $('#tli-article-editor-image-gallery');
            const target = container.find('.border-success');

            if(target.length) {
                container.animate({
                    scrollTop: target.offset().top - container.offset().top + container.scrollTop()
                }, 1000);
            }

            editorImageGallery.find('.tli-no-images-guide').fadeOut('slow', function(){
                editorImageGallery.find('.tli-images-guide').fadeIn('fast');
            });

        },
        error: function(jqXHR, textStatus, errorThrown) {

            progressBar.addClass('bg-danger');

            errorMessage
                .removeClass('collapse')
                .html(jqXHR.responseText);
        },
        complete: function() {

            thisInputFile.val('');
            progressBar.removeClass('progress-bar-animated progress-bar-striped');

            progressBarContainer.delay(3000).fadeOut('slow', function(){

                $(this).addClass('collapse');
                $(this).css('display', '');
                uploadCommandContainer.removeClass('d-none');
            });
        }
    });
});


/**
 * IMAGE INSERT LOGIC
 * ------------------
 */

let savedRange;
const editableDiv = $('.tli-article-body');

// saves the current selection (cursor position) within the editable div.
const saveSelection = () => {
    // Check if the selection is inside the editable div
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        if (editableDiv[0].contains(range.commonAncestorContainer)) {
            savedRange = range;
        }
    }
};

editableDiv.on('keyup mouseup focus', saveSelection);


$(document).on('click', '#tli-images-gallery img', function(e) {

    e.preventDefault();

    let image = $(this).clone();
    image
        .removeAttr('class')
        .removeAttr('alt')
        .removeAttr('title')
        .removeAttr('loading');

    let imageNode = $('<p>').append(image)[0];

    editableDiv.focus();

    if (savedRange) {
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(savedRange); // Restore the saved cursor position

        // Insert the image at the restored cursor position
        savedRange.insertNode(imageNode);

        // Move the cursor to be right after the newly inserted image
        savedRange.setStartAfter(imageNode);
        savedRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(savedRange);

    } else {
        // Fallback: if no cursor position was ever saved, just append the image
        editableDiv.append(imageNode);
    }
});
