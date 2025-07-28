//import $ from 'jquery';
import { fastHash16ElementHtml } from './hashing';
import debounce from './debouncer';
import StatusBar from './article-edit-statusbar';
import ArticleMeta from "./article-edit-meta";


const ArticleContentEditable = {
    save(title, body, token) {
        saveArticle(title, body, token);
    }
};

export default ArticleContentEditable;

// --------------- //

function cacheTextHashForComparison()
{
    jQuery('[data-tli-editable-id]').each(function() {

        let editableId = jQuery(this).data('tli-editable-id');
        window[editableId] = fastHash16ElementHtml(this);
    });
}

// pageload init
cacheTextHashForComparison();

jQuery(document).on('input', '[data-tli-editable-id]', debounce(function() {

    let differenceFound = false;
    jQuery('[data-tli-editable-id]').each(function() {

        let fastHashedHtml = fastHash16ElementHtml(this);
        let editableId = jQuery(this).data('tli-editable-id');

        if( fastHashedHtml != window[editableId] ) {

            StatusBar.setUnsaved();
            differenceFound = true;
            return false;
        }
    });

    if (!differenceFound) {
        StatusBar.hide();
    }

}, 300));


function clearCacheTextHashForComparison()
{
    jQuery('[data-tli-editable-id]').each(function() {

        let editableId = jQuery(this).data('tli-editable-id');
        window[editableId] = null;
    });
}


var articleSaveRequest = null;
function saveArticle(title, body, token)
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
        "title" : title ?? jQuery('[data-tli-editable-id=title]').html(),
        "body"  : body ?? jQuery('[data-tli-editable-id=body]').html(),
        "token" : token ?? null
    };

    articleSaveRequest =
        jQuery.post(endpoint, payload, function(json) {

            StatusBar.setSavedIfNotFurtherEdited(json.message);
            ArticleMeta.update(json);
            cacheTextHashForComparison();

        }, 'json')

            .fail(function(jqXHR, responseText) {

                StatusBar
                    .setError(jqXHR, responseText)
                    .showTrySaveAgain();
            });
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
