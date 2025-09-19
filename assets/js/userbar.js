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
    responseTarget.html('');

    let loaderino = jQuery(this).find('.tli-loaderino');
    loaderino.show();

    $.post( jQuery(this).attr('action'), jQuery(this).serialize() )

        .done( function(response) {
            location.reload(true);
        })

        .fail( function(response) {

            responseTarget.html(response);
            responseTarget.show();
            loaderino.hide();
        });
});
