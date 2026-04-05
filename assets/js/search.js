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


// Auto-search on format change
$(document).on('change', 'input[name="search-format"]', function() {
    performSearch();
});


function setSearchControlsDisabled(disabled) {
    SEARCH_INPUT.prop('disabled', disabled);
    $('#tli-search-button').prop('disabled', disabled);
    $('input[name="search-format"]').prop('disabled', disabled);
}


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

    let searchContainer = $('#tli-search-container');
    if( searchContainer.hasClass(IN_PROGRESS_CLASS) ) {
        return false;
    }

    searchContainer.addClass(IN_PROGRESS_CLASS);
    setSearchControlsDisabled(true);


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
    let format = $('input[name="search-format"]:checked').val();
    if( format ) url += '?format=' + format;
    searchResults.load(url, function(responseText, status, xhr){

        searchContainer.removeClass(IN_PROGRESS_CLASS);
        setSearchControlsDisabled(false);

        //$('#tli-search-container').get(0).scrollIntoView({behavior: 'smooth'});
        $('#tli-search-closing-message').removeClass('collapse');

        if (status === 'error') {

            searchResults
                .addClass('alert alert-danger')
                .html(responseText);
        }
    });
}
