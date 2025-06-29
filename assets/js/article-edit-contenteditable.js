//import $ from 'jquery';
import { fastHash16ElementHtml } from './hashing';
import debounce from './debouncer';
import StatusBar from './article-edit-statusbar';


function cacheTextHashForComparison()
{
    jQuery('[contenteditable=true]').each(function() {

        let editableId = jQuery(this).data('tli-editable-id');
        window[editableId] = fastHash16ElementHtml(this);
    });
}

// pageload init
cacheTextHashForComparison();

jQuery(document).on('input', '[contenteditable=true]', debounce(function() {

    let differenceFound = false;
    jQuery('[contenteditable=true]').each(function() {

        let fastHashedHtml = fastHash16ElementHtml(this);
        let editableId = jQuery(this).data('tli-editable-id');

        if( fastHashedHtml != window[editableId] ) {

            StatusBar.setUnsaved();
            differenceFound = true;
            return false; // break
        }
    });

    if (!differenceFound) {
        StatusBar.hide();
    }

}, 300));


function clearCacheTextHashForComparison()
{
    jQuery('[contenteditable=true]').each(function() {

        let editableId = jQuery(this).data('tli-editable-id');
        window[editableId] = null;
    });
}


var articleSaveRequest = null;

function saveArticle()
{
    // set to "unknown" until actually saved
    clearCacheTextHashForComparison();

    StatusBar.setSaving();

    if( articleSaveRequest != null ) {
        articleSaveRequest.abort();
    }

    let article = jQuery('article');
    let endpoint= article.attr('data-save-url');
    let payload = {
        "title" : jQuery('[data-tli-editable-id=title]').html(),
        "body"  : jQuery('[data-tli-editable-id=body]').html(),
        "token" : null
    };

    articleSaveRequest =

        $.post(endpoint, payload, function(response) {})

            .done(function(responseText) {

                StatusBar.setSavedIfNotFurtherEdited(responseText);
                cacheTextHashForComparison();
            })

            .fail(function(jqXHR, responseText) {

                StatusBar
                    .setError(jqXHR, responseText)
                    .showTrySaveAgain();
            })

            .always(function(response) {});
}


jQuery(document).on('click', '.tli-warning-unsaved,.tli-action-try-again',  function(event) {

    event.preventDefault();
    saveArticle();
});


jQuery(document).on('keydown', function(event) {

    // CTRL+S or Command+S (Mac)
    if( (event.ctrlKey || event.metaKey) && event.key === 's') {

        event.preventDefault();
        saveArticle();
    }
});
