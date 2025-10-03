//import $ from 'jquery';
const FADE_SPEED = 300;

$(document).on('click', '.tli-additional-thanks-button',  function(event) {

    event.preventDefault();
    $(this).fadeOut(FADE_SPEED);
    $(this).closest('.inner').find('a[data-ajax="handle_thanks"]').trigger('click');
});

