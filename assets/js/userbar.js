//import $ from 'jquery';

let userBarTarget       = jQuery('#tli-userbar-load-target');
let userbarLoadingUrl   = userBarTarget.data('loading-url');

userBarTarget.load(userbarLoadingUrl, { originUrl: window.location.href }, function() {

    let userbar = jQuery('#tli-userbar');
    if( userbar.hasClass('user-anonymous') ) {
        userBarTarget.css('position', 'sticky');
    }
});


jQuery(document).on('submit', 'form.tli-user-login', function(event) {

    event.preventDefault();

    let responseTarget = jQuery(this).find('.tli-login-response');
    responseTarget
        .removeClass('alert-danger')
        .addClass("collapse")
        .html('');

    let loaderino = jQuery(this).find('.tli-loaderino');
    loaderino.removeClass('collapse');

    $.post( jQuery(this).attr('action'), jQuery(this).serialize() )

        .done( function(response) {

            responseTarget
                .removeClass('collapse')
                .addClass('alert-success')
                .html(response || 'OK!')
                .show();

            location.reload(true);
        })

        .fail( function(response) {

            let fallbackMessage =
                '🛑 Si è verificato un errore critico. Per favore, ' +
                '<a href="/forum">esegui login tramite il forum</a>';

            responseTarget
                .removeClass('collapse')
                .addClass('alert-danger')
                .html(response.responseText || fallbackMessage)
                .show();
        })

        .always(function(){
            loaderino.addClass('collapse');
        })
});
