//import $ from 'jquery';
import debounce from "./debouncer";


jQuery(document).on('click', '.tli-remove-author',  function(event) {

    event.preventDefault();

    let currentAuthorsList = $('.tli-article-editor-current-authors-list');
    currentAuthorsList.data('changed', 1);

    let authorInList= $(this).closest('.list-group-item');

    authorInList.fadeOut('slow', function(){

        let authorId    = authorInList.data('author-id');

        authorInList.remove();

        let authorsNum = currentAuthorsList.find('[data-author-id]').length;
        currentAuthorsList.find('.tli-no-author-message').toggleClass('collapse', authorsNum != 0);

        let candidateUserContainer = jQuery('.tli-article-editor-candidate-authors-list [data-author-id='+ authorId + ']');
        if( candidateUserContainer.length == 0 ) {
            return true;
        }

        candidateUserContainer.find('.tli-add-author').removeClass('d-none');
        candidateUserContainer.find('.tli-author-already').addClass('d-none');
    });
});


jQuery(document).on('click', '.tli-add-author',  function(event) {

    event.preventDefault();

    let currentAuthorsList = $('.tli-article-editor-current-authors-list');
    currentAuthorsList.data('changed', 1);

    alert("ADDING...");
});


jQuery(document).on('input', 'input.tli-authors-autocomplete', debounce(function() {

    let username = jQuery(this).val().trim();

    let container = jQuery('.tli-authors-autocomplete-container');
    let target = container.find('.tli-article-editor-candidate-authors-list');

    if( username.length > 0 && username.length < 3 ) {

        target.html('');
        return;
    }

    loadAuthors(username);

}, 350));


var userSearchRequest = null;

function loadAuthors(username)
{
    if( userSearchRequest != null ) {
        userSearchRequest.abort();
    }

    let container = jQuery('.tli-authors-autocomplete-container');

    let target = container.find('.tli-article-editor-candidate-authors-list');
    target.hide();
    target.html();

    // show loaderino
    let loaderino = container.find('.tli-loaderino').closest('div');
    loaderino.removeClass('d-none');

    // fetch results
    let endpoint = container.data('autocomplete-url');

    userSearchRequest =

        jQuery.get(endpoint, {username: username}, function(data) {

            target.html(data);

            jQuery('.tli-article-editor-current-authors-list [data-author-id]').each(function() {

                let userContainer = target.find('[data-author-id='+ jQuery(this).data('author-id') +']');
                if( userContainer.length == 0 ) {
                    return true;
                }

                userContainer.find('.tli-add-author').addClass('d-none');
                userContainer.find('.tli-author-already').removeClass('d-none');
            });

        }, 'html')

            .fail(function(jqXHR, responseText) {

                if(responseText != 'abort') {
                    target.html(jqXHR.responseText);
                }
            })

            .always(function(jqXHR, responseText) {

                target.show();
                loaderino.addClass('d-none');
            });
}


jQuery(document).on('click', '.tli-author-button-ok',  function(event) {

    event.preventDefault();
    let currentAuthorsList = $('.tli-article-editor-current-authors-list');

    if( currentAuthorsList.data("changed") == "0" ) {

        $('.tli-author-button-cancel').trigger('click');
        return false;
    }

    if( currentAuthorsList.find('[data-author-id]').length == 0 ) {

        alert("L\'articolo deve avere almeno 1 autore");
        return false;
    }

    alert("CHANGED, SAVING");
});
