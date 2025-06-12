//import $ from 'jquery';

jQuery(document).on('input', '[contenteditable="true"]', function(event) {

    let unsavedMessage = jQuery('#tli-unsaved-warning');

    if( !unsavedMessage.hasClass('collapse') ) {
        return false;
    }

    let currentText = jQuery(this).html();
    let editableId  = jQuery(this).data('tli-editable-id');

    if (typeof window[editableId] === 'undefined') {
        window[editableId] = '';
    }

    if( currentText != window[editableId] ) {
        unsavedMessage.fadeIn('slow');
    }
});


jQuery(document).on('click', '#tli-unsaved-warning', function(event) {

    event.preventDefault();
    jQuery(this).fadeOut('slow', function() {
        jQuery(this).addClass('collapse');
    });
});
