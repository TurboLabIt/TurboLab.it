//import $ from 'jquery';
import { fastHash16ElementHtml } from './js/hashing';
import debounce from './js/debouncer';


function cacheTextHashForComparison()
{
    jQuery('[contenteditable=true]').each(function() {

        let editableId = jQuery(this).data('tli-editable-id');
        window[editableId] = fastHash16ElementHtml(this);
    });
}

// pageload init
cacheTextHashForComparison();


function setArticleSavingStatusBar(alertClass, showUnsavedTextMessage, showLoaderino, showTryAgain, responseText)
{
    let articleSavingStatusBar = jQuery('#tli-article-saving-status-bar');

    let alertContainer = articleSavingStatusBar.find('.alert');
    alertContainer
        .removeClass('alert-primary alert-success alert-danger alert-warning')
        .addClass(alertClass);

    let textContainer = articleSavingStatusBar.find('.tli-warning-unsaved');
    showUnsavedTextMessage ? textContainer.removeClass('collapse') : textContainer.addClass('collapse');

    let loaderino = articleSavingStatusBar.find('.tli-loaderino');
    showLoaderino ? loaderino.removeClass('collapse') : loaderino.addClass('collapse');

    let responseTarget = articleSavingStatusBar.find('.tli-response-target');

    if( responseText === 0 ) {

        responseTarget.addClass('collapse');

    } else {

        responseTarget
            .removeClass('collapse')
            .html(responseText);
    }

    let tryAgain = articleSavingStatusBar.find('.tli-action-try-again');
    showTryAgain ? tryAgain.removeClass('collapse') : tryAgain.addClass('collapse');
}


jQuery(document).on('input', '[contenteditable=true]', debounce(function() {

    let articleSavingStatusBar = jQuery('#tli-article-saving-status-bar');

    let differenceFound = false;
    jQuery('[contenteditable=true]').each(function() {

        let fastHashedHtml = fastHash16ElementHtml(this);
        let editableId = jQuery(this).data('tli-editable-id');

        if( fastHashedHtml != window[editableId] ) {

            setArticleSavingStatusBar('alert-danger', 1, 0, 0, 0);
            articleSavingStatusBar.show();
            differenceFound = true;
            return false; // break
        }
    });

    if (!differenceFound) {
        articleSavingStatusBar.hide();
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
    let articleSavingStatusBar = jQuery('#tli-article-saving-status-bar');

    // set to "unknown" until actually saved
    clearCacheTextHashForComparison();

    articleSavingStatusBar.show();
    setArticleSavingStatusBar('alert-primary', 0, 1, 0, 0);

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

    let unsavedWarning = articleSavingStatusBar.find('.tli-warning-unsaved');

    articleSaveRequest =

        $.post(endpoint, payload, function(response) {})

            .done(function(responseText) {

                if( !unsavedWarning.is(':visible') ) {
                    setArticleSavingStatusBar('alert-success', 0, 0, 0, responseText);
                }

                cacheTextHashForComparison();
            })

            .fail(function(jqXHR, responseText) {

                if(responseText != 'abort') {
                    setArticleSavingStatusBar('alert-danger', 0, 0, 1, jqXHR.responseText);
                }
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
