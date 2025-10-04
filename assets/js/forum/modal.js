//import $ from 'jquery';
const FADE_SPEED = 300;

$(document).on('click', '.tli-forum-modal .alert_close',  function(event) {

    event.preventDefault();
    $('#tli-darken').fadeOut(FADE_SPEED);
    $(this).closest('.tli-forum-modal').fadeOut(FADE_SPEED);
    $('body').removeClass('tli-prevent-scrolling');
});
