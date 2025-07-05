//import $ from 'jquery';
import debounce from "./debouncer";
import StatusBar from './article-edit-statusbar';
import ArticleMeta from './article-edit-meta';


jQuery(document).on('click', '.tli-remove-tag',  function(event) {

    event.preventDefault();

    let currentTagsList = $(this).closest('.tli-tags-strip');
    currentTagsList.data('changed', 1);

    let tagInList= $(this).closest('li');

    tagInList.fadeOut('slow', function(){

        let tagId = tagInList.data('tag-id');

        tagInList.remove();

        let tagsNum = currentTagsList.find('[data-tag-id]').length;
        currentTagsList.parent().find('.alert-warning').toggleClass('collapse', tagsNum != 0);

        /*let candidateUserContainer = jQuery('.tli-article-editor-candidate-tags-list [data-tag-id='+ tagId + ']');
        if( candidateUserContainer.length == 0 ) {
            return true;
        }

        candidateUserContainer.find('.tli-add-tag').removeClass('d-none');
        candidateUserContainer.find('.tli-tag-already').addClass('d-none');*/
    });
});


jQuery(document).on('click', '.tli-add-tag',  function(event) {

    event.preventDefault();

    let currentTagsList = $(this).closest('.tli-tags-strip');
    currentTagsList.data('changed', 1);
    currentTagsList.find('.tli-no-tag-message').addClass('collapse');

    let clickedUserContainer = jQuery(this).closest('[data-tag-id]');
    let clickedUserContainerCopy = clickedUserContainer.clone();

    clickedUserContainer.find('.tli-add-tag').addClass('d-none');
    clickedUserContainer.find('.tli-tag-already').removeClass('d-none')

    clickedUserContainerCopy.find('.tli-add-tag').addClass('d-none');
    clickedUserContainerCopy.find('.tli-remove-tag').removeClass('d-none');
    currentTagsList.append(clickedUserContainerCopy);
});


jQuery(document).on('input', 'input.tli-tags-autocomplete', debounce(function() {

    let username = jQuery(this).val().trim();

    let container = jQuery('.tli-tags-autocomplete-container');
    let target = container.find('.tli-article-editor-candidate-tags-list');

    if( username.length > 0 && username.length < 3 ) {

        target.html('');
        return;
    }

    loadTags(username);

}, 350));


var userSearchRequest = null;

function loadTags(username)
{
    if( userSearchRequest != null ) {
        userSearchRequest.abort();
    }

    let container = jQuery('.tli-tags-autocomplete-container');

    let target = container.find('.tli-article-editor-candidate-tags-list');
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

            jQuery('.tli-article-editor-current-tags-list [data-tag-id]').each(function() {

                let userContainer = target.find('[data-tag-id='+ jQuery(this).data('tag-id') +']');
                if( userContainer.length == 0 ) {
                    return true;
                }

                userContainer.find('.tli-add-tag').addClass('d-none');
                userContainer.find('.tli-tag-already').removeClass('d-none');
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


jQuery(document).on('click', '.tli-tag-button-ok',  function(event) {

    event.preventDefault();
    let currentTagsList = jQuery('#tli-ajax-modal').find('.tli-tags-strip');

    if( currentTagsList.data("changed") == "0" ) {

        $('.tli-tag-button-cancel').trigger('click');
        return false;
    }

    if( currentTagsList.find('[data-tag-id]').length == 0 ) {

        alert("L\'articolo deve avere almeno 1 tag");
        return false;
    }

    let tagIds =
        currentTagsList.find('[data-tag-id]').map(function() {
            return $(this).data('tag-id');
        }).get();


    jQuery('#tli-ajax-modal').find('.btn-close').trigger('click');
    StatusBar.setSaving();

    let endpoint = currentTagsList.data("save-url");

    jQuery.post(endpoint, {tags: tagIds}, function(json) {
debugger;
        StatusBar.setSaved(json.message);
        ArticleMeta.update(json);

    }, 'json')

        .fail(function(jqXHR, responseText) {
            debugger;
            StatusBar.setError(jqXHR, responseText);
        });
});
