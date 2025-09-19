//import $ from 'jquery';
const FADE_SPEED        = 300;
const ISSUE_MODAL       = $('#tli-issue-modal');
const IN_PROGRESS_CLASS = 'tli-issue-action-running';


$(document).on('click', '.tli-open-issue-modal',  function(event) {

    event.preventDefault();

    if( ISSUE_MODAL.hasClass(IN_PROGRESS_CLASS) ) {

        alert("Creazione issue in corso. Potrai crearne un'altra fra poco");
        return false;
    }

    $('#tli-darken').fadeIn(FADE_SPEED);
    $('#tli-issue-modal').fadeIn(FADE_SPEED);

    let postId = $(this).data('post-id');
    ISSUE_MODAL.find('.tli-create-issue').data('post-id', postId);
});


$(document).on('click', '.tli-create-issue',  function(event) {

    let clickedButton = $(this);

    event.preventDefault();

    ISSUE_MODAL.addClass(IN_PROGRESS_CLASS);

    let submitters = ISSUE_MODAL.find('.button-container');
    submitters.fadeOut(FADE_SPEED);

    let loaderinoHtml = ISSUE_MODAL.find('.tli-loaderino-container').html();

    let responseTarget =
        ISSUE_MODAL.find('.tli-response-target')
            .removeClass('collapse alert-success alert-danger')
            .addClass('alert-warning')
            .html(loaderinoHtml);

    let postId      = clickedButton.data('post-id');
    let bugButton   = $('.tli-bug-button-' + postId);

    let bugIcon = bugButton.find('i');
    bugIcon.addClass("fa-spin");

    $.post(clickedButton.data('url'), {postId : postId})

        .done( function(response) {

            responseTarget.html("OK!").addClass("alert-success");

            if (window.location.href == response) {

                window.location.reload();

            } else {

                window.location.href = response;
            }
        })

        .fail( function(jqXHR, responseText) {

            responseTarget.html(jqXHR.responseText).addClass("alert-danger");
            submitters.fadeIn(FADE_SPEED);
        })

        .always(function(){

            responseTarget.removeClass('alert-warning');
            bugIcon.removeClass("fa-spin");
            ISSUE_MODAL.removeClass(IN_PROGRESS_CLASS);
        });
});


$(document).on('click', '#tli-issue-modal .alert_close, #tli-issue-modal .button2',  function(event) {

    event.preventDefault();
    $('#tli-issue-modal').fadeOut(FADE_SPEED);
    $('#tli-darken').fadeOut(FADE_SPEED);
});
