//import $ from 'jquery';

$(document).on('click', '.tli-additional-thanks-button',  function(event) {

    event.preventDefault();
    $(this).closest('.inner').find('a[data-ajax="handle_thanks"]').trigger('click');
});


$(document).on('click', 'a[data-ajax="handle_thanks"]',  function(event) {
    $(this).closest('.inner').find('.tli-additional-thanks-button').fadeOut(300);
});
