//import $ from 'jquery';
import { fastHash16ElementHtml } from './hashing';
import debounce from './debouncer';
import StatusBar from './article-edit-statusbar';
import ArticleMeta from "./article-edit-meta";


const ArticleContentEditable = {
    save(title, body, token) {
        saveArticle(title, body, token);
    },
    cacheTextHashForComparison() {
        cacheTextHashForComparison();
    },
    showWarningIfChanged() {
        showWarningIfChanged();
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


function showWarningIfChanged()
{
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
}


function clearCacheTextHashForComparison()
{
    jQuery('[data-tli-editable-id]').each(function() {

        let editableId = jQuery(this).data('tli-editable-id');
        window[editableId] = null;
    });
}


jQuery(document).on('input', 'h1[data-tli-editable-id]', debounce(function() {
    showWarningIfChanged();
}, 300));


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
    //saveArticle();
    $('#tli-ckeditor-save').trigger('click');
});



jQuery(document).on('keydown', function(event) {

    // CTRL+S or Command+S (Mac)
    if( (event.ctrlKey || event.metaKey) && (event.key === 's' || event.key === 'S') ) {

        event.preventDefault();
        $('#tli-ckeditor-save').first().trigger('click');
        return false;
    }

    // CTRL+1 or Command+1 (Mac)
    if( (event.ctrlKey || event.metaKey) && event.key === '1' ) {

        event.preventDefault();
        $('[data-cke-tooltip-text="Titolo (Ctrl+1)"]').first().trigger('click');
        return false;
    }

    // CTRL+M or Command+M (Mac)
    if( (event.ctrlKey || event.metaKey) && (event.key === 'm' || event.key === 'M') ) {

        event.preventDefault();
        $('[data-cke-tooltip-text="Istruzioni (Ctrl+M)"]').first().trigger('click');
        return false;
    }

    return true;
});
