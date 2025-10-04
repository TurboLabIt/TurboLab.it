//import $ from 'jquery';
const FADE_SPEED        = 300;
const LINK_ARTICLE_MODAL= $('#tli-link-article-modal');
const SEARCH_INPUT      = $('#tli-search-input');
const TERM_MIN_LENGTH   = $('#tli-search-input').attr('minlength');
const TOO_SHORT_ERROR   = $('#tli-search-too-short-error');
const IN_PROGRESS_CLASS = 'tli-search-running';



// CTRL+K or Command+K (Mac)
$(document).keydown(function(event) {
    if( (event.ctrlKey || event.metaKey) && (event.key === 'k' || event.key === 'K') ) {
        event.preventDefault();
        $('.tli-open-link-article-modal').trigger('click');
    }
});


$(document).on('click', '.tli-open-link-article-modal',  function(event) {

    event.preventDefault();

    $('#tli-darken').fadeIn(FADE_SPEED);

    $(LINK_ARTICLE_MODAL).fadeIn(FADE_SPEED, function() {
        $(this).addClass('tli-display-flex');
    });

    $('body').addClass('tli-prevent-scrolling');
    $(SEARCH_INPUT).focus();
});


$(document).on('keypress', '#tli-search-input',  function(event) {

    if (event.which === 13 || event.keyCode === 13) {

        event.preventDefault();
        performSearch();

    } else if( SEARCH_INPUT.val().trim().length >= (TERM_MIN_LENGTH-1) ) {

        TOO_SHORT_ERROR
            .addClass('ml-5')
            .removeClass('alert alert-danger');
    }
});


$(document).on('click', '#tli-search-button',  function(event) {

    event.preventDefault();
    performSearch();
});


function performSearch()
{
    let term = SEARCH_INPUT.val().trim();
    if( term.length < TERM_MIN_LENGTH ) {

        TOO_SHORT_ERROR
            .removeClass('ml-5')
            .addClass('alert alert-danger');

        return false;
    }

    // handle the selection of an entry from the browser history
    TOO_SHORT_ERROR
        .addClass('ml-5')
        .removeClass('alert alert-danger');


    if( LINK_ARTICLE_MODAL.hasClass(IN_PROGRESS_CLASS) ) {

        alert('Ricerca in corso. Potrai cercare di nuovo fra un attimo');
        return false;
    }

    LINK_ARTICLE_MODAL.addClass(IN_PROGRESS_CLASS);

    let searchResults = $('#tli-search-results');
    searchResults
        .removeClass('alert alert-danger')
        .html('');

    let loaderino =
        LINK_ARTICLE_MODAL.find('.tli-loaderino')
            .clone()
            .removeClass('collapse')
            .prop('outerHTML');

    searchResults.html(loaderino);

    // ajax data loading
    let url = searchResults.data('url') + '/' + encodeURIComponent(term);
    searchResults.load(url, function(responseText, status, xhr){

        LINK_ARTICLE_MODAL.removeClass(IN_PROGRESS_CLASS);

        if (status === 'error') {

            searchResults
                .addClass('alert alert-danger')
                .html(responseText);
        }
    });
}


$(document).on('click', '.tli-link-article-bbcode',  function(event) {

    event.preventDefault();

    let bbcode = $(this).data('bbcode');
    insert_text(bbcode, true);

    $(this).closest('.tli-forum-modal').find('.alert_close').trigger('click');
});
