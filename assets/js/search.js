//import $ from 'jquery';
const SEARCH_INPUT      = $('#tli-search-input');
const TERM_MIN_LENGTH   = $('#tli-search-input').attr('minlength');
const TOO_SHORT_ERROR   = $('#tli-search-too-short-error');
const IN_PROGRESS_CLASS = 'tli-search-running';


if( SEARCH_INPUT.val() != '' ) {
    performSearch();
}


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


    let searchContainer = $('#tli-search-container');
    if( searchContainer.hasClass(IN_PROGRESS_CLASS) ) {

        alert('Ricerca in corso. Potrai cercare di nuovo fra un attimo');
        return false;
    }

    searchContainer.addClass(IN_PROGRESS_CLASS);


    $('#tli-search-closing-message').addClass('collapse');

    let searchResults = $('#tli-search-results');
    searchResults
        .removeClass('alert alert-danger')
        .html('');

    let loaderino =
        $('#tli-search-results').closest('.container').find('.tli-loaderino')
            .clone()
            .removeClass('collapse')
            .prop('outerHTML');

    searchResults.html(loaderino);

    //update the current page url
    let serpUrl = searchContainer.data('url') + '/' + encodeURIComponent(term);
    history.pushState({}, "", serpUrl);

    // ajax data loading
    let url = searchResults.data('url') + '/' + encodeURIComponent(term);
    searchResults.load(url, function(responseText, status, xhr){

        searchContainer.removeClass(IN_PROGRESS_CLASS);

        //$('#tli-search-container').get(0).scrollIntoView({behavior: 'smooth'});
        $('#tli-search-closing-message').removeClass('collapse');

        if (status === 'error') {

            searchResults
                .addClass('alert alert-danger')
                .html(responseText);
        }
    });
}
