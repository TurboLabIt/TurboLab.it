//import $ from 'jquery';

let userBarTarget       = jQuery('#tli-userbar-load-target');
let userbarLoadingUrl   = userBarTarget.data('loading-url');

jQuery('#tli-userbar-load-target').load(
    userbarLoadingUrl, { originUrl: window.location.href }, function() {

    let userbar = jQuery('#tli-userbar');
    if( userbar.hasClass('user-anonymous') ) {
        userBarTarget.css('position', 'sticky');
    }
});
