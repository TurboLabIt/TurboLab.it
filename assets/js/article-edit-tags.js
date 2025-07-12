//import $ from 'jquery';
import debounce from "./debouncer";
import StatusBar from './article-edit-statusbar';
import ArticleMeta from './article-edit-meta';


jQuery(document).on('tli-tag-modal-open', '.tli-article-editor-current-tags-list',  function(event) {

    let currentTagsList = jQuery('#tli-ajax-modal').find('.tli-article-editor-current-tags-list');
    let tagIds =
        currentTagsList.find('[data-tag-id]').map(function() {
            return $(this).data('tag-id');
        }).get();

    if(tagIds.length === 0) {
        return false;
    }

    jQuery('#tli-ajax-modal').find('.tli-tags-candidate .tli-add-tag').closest('li').each(function(){

        let tag = jQuery(this);
        let tagId = tag.data('tag-id');

        if( jQuery.inArray(tagId, tagIds) != -1 ) {
            tag.addClass('d-none');
        }
    });

    openSuggestions(true);
});


jQuery(document).on('click', '.tli-article-editor-show-suggested-tags-action',  function(event) {

    event.preventDefault();
    openSuggestions( jQuery(this).data('status') == 0 );
});


function openSuggestions(open)
{
    let showSuggestionAction = jQuery('#tli-ajax-modal').find('.tli-article-editor-show-suggested-tags-action');
    let suggestions = jQuery('#tli-ajax-modal').find('.tli-tags-suggestion-collapsable');

    if(open) {

        showSuggestionAction.html( showSuggestionAction.data('hide-text') );
        showSuggestionAction.data('status', 1);
        suggestions.fadeIn();
        jQuery('#tli-style-tags-suggestion-closed').remove();

    } else {

        showSuggestionAction.html( showSuggestionAction.data('show-text') );
        showSuggestionAction.data('status', 0);
        suggestions.fadeOut();

        jQuery('head').append(
            '<style id="tli-style-tags-suggestion-closed">.tli-article-editor-search-result .tli-tags-candidate {max-height: 600px}</style>'
        );
    }
}


jQuery(document).on('click', '.tli-remove-tag',  function(event) {

    event.preventDefault();

    let currentTagsList = $(this).closest('.tli-article-editor-current-tags-list');
    currentTagsList.data('changed', 1);

    let tagInList= $(this).closest('li');

    tagInList.fadeOut('fast', function(){

        let removedTagId = tagInList.data('tag-id');

        tagInList.remove();

        let tagsNum = currentTagsList.find('[data-tag-id]').length;
        currentTagsList.parent().find('.alert-warning').toggleClass('collapse', tagsNum != 0);

        jQuery('#tli-ajax-modal').find('.tli-tags-candidate .tli-add-tag').closest('li').each(function(){

            let tag = jQuery(this);
            let candidateTag = tag.data('tag-id');

            if( candidateTag == removedTagId ) {
                tag.removeClass('d-none');
            }
        });
    });
});


jQuery(document).on('click', '.tli-add-tag',  function(event) {

    event.preventDefault();

    let currentTagsList = jQuery('#tli-ajax-modal').find('.tli-article-editor-current-tags-list');
    currentTagsList.data('changed', 1);
    currentTagsList.parent().find('.alert-warning').addClass('collapse');

    let clickedTagContainer = jQuery(this).closest('[data-tag-id]');
    const clickedTagContainerOffset = clickedTagContainer.offset();

    let flyingItem = clickedTagContainer.clone().attr('id', 'flyingItem');
    flyingItem.addClass('tli-flying-item').css({
        left:       clickedTagContainerOffset.left,
        top:        clickedTagContainerOffset.top
    });

    jQuery('body').append(flyingItem);
    clickedTagContainer.addClass("d-none");

    let targetList = currentTagsList.find('ul');
    let targetListLastItem = targetList.find('li:last-child');

    flyingItem.animate({
        left:   targetListLastItem.offset().left + targetListLastItem.width(),
        top:    targetListLastItem.offset().top,
        opacity: 0.5
    }, 500, function() {

        flyingItem.remove();

        let clickedTagContainerCopy = clickedTagContainer.clone();
        clickedTagContainerCopy.removeClass('d-none');
        clickedTagContainerCopy.find('.tli-add-tag').addClass('d-none');
        clickedTagContainerCopy.find('.tli-remove-tag').removeClass('d-none');

        targetList.append(clickedTagContainerCopy);
    });
});


jQuery(document).on('input', 'input.tli-tags-autocomplete', debounce(function() {

    let tag = jQuery(this).val().trim();

    let container = jQuery('.tli-tags-autocomplete-container');
    let target = container.find('.tli-article-editor-search-result');

    if( tag.length < 3 ) {

        target.html('');
        return;
    }

    openSuggestions(false);

    loadTags(tag);

}, 350));


var tagSearchRequest = null;

function loadTags(tag)
{
    if( tagSearchRequest != null ) {
        tagSearchRequest.abort();
    }

    let container = jQuery('.tli-tags-autocomplete-container');

    let target = container.find('.tli-article-editor-search-result');
    target.hide();
    target.html();

    // show loaderino
    let loaderino = container.find('.tli-loaderino').closest('div');
    loaderino.removeClass('d-none');

    // fetch results
    let endpoint = container.data('autocomplete-url');

    tagSearchRequest =

        jQuery.get(endpoint, {tag: tag}, function(data) {

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


jQuery(document).on('click', '.tli-tag-button-ok',  function(event) {

    event.preventDefault();
    let currentTagsList = jQuery('#tli-ajax-modal').find('.tli-article-editor-current-tags-list');

    if( currentTagsList.data("changed") == "0" ) {

        $('.tli-tag-button-cancel').trigger('click');
        return false;
    }

    if( currentTagsList.find('[data-tag-id]').length == 0 ) {

        alert("L\'articolo deve avere almeno 1 tag");
        return false;
    }

    let tags =
        currentTagsList.find('[data-tag-id]').map(function() {
            return { 'id': $(this).data('tag-id'), 'title': $(this).data('tag') };
        }).get();


    jQuery('#tli-ajax-modal').find('.btn-close').trigger('click');
    StatusBar.setSaving();

    let endpoint = currentTagsList.data("save-url");

    jQuery.post(endpoint, {tags: tags}, function(json) {

        StatusBar.setSaved(json.message);
        ArticleMeta.update(json);

    }, 'json')

        .fail(function(jqXHR, responseText) {
            StatusBar.setError(jqXHR, responseText);
        });
});
