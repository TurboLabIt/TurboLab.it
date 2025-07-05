//import $ from 'jquery';
import debounce from "./debouncer";
import StatusBar from './article-edit-statusbar';
import ArticleMeta from './article-edit-meta';


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
    currentAuthorsList.find('.tli-no-author-message').addClass('collapse');

    let clickedUserContainer = jQuery(this).closest('[data-author-id]');
    let clickedUserContainerCopy = clickedUserContainer.clone();

    clickedUserContainer.find('.tli-add-author').addClass('d-none');
    clickedUserContainer.find('.tli-author-already').removeClass('d-none')

    clickedUserContainerCopy.find('.tli-add-author').addClass('d-none');
    clickedUserContainerCopy.find('.tli-remove-author').removeClass('d-none');
    currentAuthorsList.append(clickedUserContainerCopy);
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

    let authorIds =
        currentAuthorsList.find('[data-author-id]').map(function() {
            return $(this).data('author-id');
        }).get();


    jQuery('#tli-ajax-modal').find('.btn-close').trigger('click');
    StatusBar.setSaving();

    let endpoint = currentAuthorsList.data("save-url");

    jQuery.post(endpoint, {authors: authorIds}, function(json) {

        StatusBar.setSaved(json.message);
        ArticleMeta.update(json);

    }, 'json')

        .fail(function(jqXHR, responseText) {
            StatusBar.setError(jqXHR, responseText);
        });
});
