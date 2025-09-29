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

const TITLE_FIELD = $('[data-tli-editable-id="title"]');


function cacheTextHashForComparison()
{
    let editableId      = TITLE_FIELD.data('tli-editable-id');
    window[editableId]  = fastHash16ElementHtml( TITLE_FIELD.val() );

    let bodyField       = $('[data-tli-editable-id="body"]');
    editableId          = bodyField.data('tli-editable-id');
    window[editableId]  = fastHash16ElementHtml( bodyField.html() );
}


function showWarningIfChanged()
{
    let fastHashedHtml  = fastHash16ElementHtml( TITLE_FIELD.val() );
    let editableId      = TITLE_FIELD.data('tli-editable-id');
    let differenceFound = fastHashedHtml != window[editableId];

    if( !differenceFound ) {

        let bodyField   = $('[data-tli-editable-id="body"]');
        fastHashedHtml  = fastHash16ElementHtml( bodyField.html() );
        editableId      = bodyField.data('tli-editable-id');
        differenceFound = fastHashedHtml != window[editableId];
    }

    if(differenceFound) {

        StatusBar.setUnsaved();

    } else {

        StatusBar.hide();
    }
}


function clearCacheTextHashForComparison()
{
    let editableId      = TITLE_FIELD.data('tli-editable-id');
    window[editableId]  = null;

    let bodyField       = $('[data-tli-editable-id="body"]');
    editableId          = bodyField.data('tli-editable-id');
    window[editableId]  = null
}


// title auto-sizing
TITLE_FIELD.css({

    height: TITLE_FIELD[0].scrollHeight + "px",
    overflowY: "hidden"
});

$(document).on('input', '[data-tli-editable-id="title"]', function() {

    // remove <new-line> and double spaces
    const cursorPosition = this.selectionStart;
    const originalLength = this.value.length;

    const newValue = this.value.replace(/\s+/g, ' ');

    if (this.value !== newValue) {
        this.value = newValue;
        // Adjust cursor position
        const newLength = this.value.length;
        const diff = originalLength - newLength;
        this.selectionStart = this.selectionEnd = Math.max(0, cursorPosition - diff);
    }

    // auto-height
    this.style.height = "auto";
    this.style.height = this.scrollHeight + "px";

});


$(document).on('input', '[data-tli-editable-id="title"]', debounce(function() {
    showWarningIfChanged();
}, 350));


var articleSaveRequest = null;
function saveArticle(title, body, token)
{
    // set to "unknown" until actually saved
    clearCacheTextHashForComparison();

    StatusBar.setSaving();

    if( articleSaveRequest != null ) {
        articleSaveRequest.abort();
    }

    let article = $('article');
    let endpoint= article.attr('data-save-url');
    let payload = {
        "title" : title ?? $('[data-tli-editable-id="title"]').val(),
        "body"  : body ?? $('[data-tli-editable-id="body"]').html(),
        "token" : token ?? null
    };

    articleSaveRequest =
        $.post(endpoint, payload, function(json) {

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


$(document).on('click', '.tli-warning-unsaved,.tli-action-try-again',  function(event) {

    event.preventDefault();
    $('#tli-ckeditor-save').trigger('click');
});


$(document).on('keydown', function(event) {

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
