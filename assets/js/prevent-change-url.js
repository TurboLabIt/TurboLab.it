//import $ from 'jquery';

jQuery('.prevent-change-url').click(function() {

    let originalUrl = window.location.href;
    setTimeout(function() {
        history.replaceState(null, null, originalUrl);
    }, 500);
});


jQuery('a.disabled-link').click(function(event) {

    event.preventDefault();
    return false;
});
