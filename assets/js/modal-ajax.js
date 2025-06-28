//import $ from 'jquery';
//import * as bootstrap from 'bootstrap';


jQuery(document).on('click', '[data-tli-modal-url]',  function(event) {

    event.preventDefault();

    let endpoint    = jQuery(this).data('tli-modal-url');

    let modalFrame  = jQuery('#tli-ajax-modal');

    let targetTitle = modalFrame.find('.modal-title');
    targetTitle.html('');

    let loaderino =
        modalFrame.find('.tli-modal-loading')
            .clone().removeClass('d-none').prop('outerHTML');

    let targetBody = modalFrame.find('.tli-ajax-modal-content');
    targetBody.html('');
    targetBody.html(loaderino);

    new bootstrap.Modal(modalFrame).show();

    jQuery.get(endpoint, function(data) {

        targetTitle.html(data.title);
        targetBody.html(data.body);

    }, 'json')

        .fail(function(jqXHR, textStatus, errorThrown) {

            targetTitle.html('ERRORE');
            targetBody.html(jqXHR.responseText);
        });
});
