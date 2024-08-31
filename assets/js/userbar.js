//import $ from 'jquery';

let userbarLoadingUrl = jQuery('#tli-userbar-load-target').data('loading-url');
jQuery('#tli-userbar-load-target').load(userbarLoadingUrl, {
    originUrl: window.location.href
});
