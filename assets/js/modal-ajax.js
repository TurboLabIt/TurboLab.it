//import $ from 'jquery';
//import * as bootstrap from 'bootstrap';


export default function openAjaxModal(endpoint)
{
    let modalFrame  = jQuery('#tli-ajax-modal');

    let targetTitle = modalFrame.find('.modal-title');
    targetTitle.html('');

    let loaderino =
        modalFrame.find('.tli-modal-loading')
            .clone().removeClass('d-none').prop('outerHTML');

    let targetBody = modalFrame.find('.tli-ajax-modal-content');
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
}


/**
 * No loader: the modal stays closed while loading and opens only if shouldShow(data) is true,
 * for automatic checks that speak up only when they have something to report.
 * A failed request opens it anyway: a check that couldn't run must not pass for a clean one
 */
export function openAjaxModalIf(endpoint, shouldShow)
{
    let modalFrame = jQuery('#tli-ajax-modal');

    let show = function(title, body) {

        modalFrame.find('.modal-title').html(title);
        modalFrame.find('.tli-ajax-modal-content').html(body);
        new bootstrap.Modal(modalFrame).show();
    };

    jQuery.get(endpoint, function(data) {

        if( shouldShow(data) ) {
            show(data.title, data.body);
        }

    }, 'json')

        .fail(function(jqXHR, textStatus, errorThrown) {
            show('ERRORE', jqXHR.responseText);
        });
}


jQuery(document).on('click', '[data-tli-modal-url]',  function(event) {

    event.preventDefault();
    openAjaxModal( jQuery(this).data('tli-modal-url') );
});
