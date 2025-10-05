//import $ from 'jquery';

$(document).on('click', '.prevent-change-url', function() {

    let originalUrl = window.location.href;
    setTimeout(function() {
        history.replaceState(null, null, originalUrl);
    }, 500);
});


$(document).on('click', 'a.disabled-link', function(event) {

    event.preventDefault();
    return false;
});
