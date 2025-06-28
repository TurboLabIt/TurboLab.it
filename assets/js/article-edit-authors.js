//import $ from 'jquery';
import debounce from "./debouncer";


jQuery(document).on('click', '.tli-remove-author',  function(event) {

    event.preventDefault();

    let currentAuthorsList = $('#tli-ajax-modal .tli-article-editor-current-authors-list');
    let authorInList= $(this).closest('.list-group-item');
    //let authorId    = authorInList.data['author-id'];
    authorInList.fadeOut('slow', function(){

        authorInList.remove();
        let authorsNum = currentAuthorsList.find('[data-author-id]').length;
        currentAuthorsList.find('.tli-no-author-message').toggleClass('collapse', authorsNum != 0);
    });
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

loadAuthors();
