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


// remove <new-line> and double spaces from the title field; returns true if something was cleaned
function cleanTitleField()
{
    let titleField = TITLE_FIELD[0];
    if( !titleField ) {
        return false;
    }

    const cleaned = titleField.value.replace(/\s+/g, ' ');
    if( titleField.value === cleaned ) {
        return false;
    }

    titleField.value = cleaned;
    // auto-height recalc since the value may have shrunk
    titleField.style.height = "auto";
    titleField.style.height = titleField.scrollHeight + "px";
    return true;
}


// title auto-sizing
TITLE_FIELD.css({

    height: TITLE_FIELD[0].scrollHeight + "px",
    overflowY: "hidden"
});

$(document).on('input', '[data-tli-editable-id="title"]', function() {

    // auto-height
    this.style.height = "auto";
    this.style.height = this.scrollHeight + "px";

});


$(document).on('blur', '[data-tli-editable-id="title"]', function() {

    if( cleanTitleField() ) {
        // refresh the unsaved-warning state in case the cleaned value now matches the saved hash
        showWarningIfChanged();
    }
});


$(document).on('input', '[data-tli-editable-id="title"]', debounce(function() {
    showWarningIfChanged();
}, 350));


var articleSaveRequest = null;
function saveArticle(title, body, token)
{
    // Block saves while pasted images are still being uploaded, otherwise the body
    // would be persisted with huge "data:image/...;base64,..." payloads.
    if( typeof window.TLI_PENDING_IMAGE_UPLOADS === 'function' && window.TLI_PENDING_IMAGE_UPLOADS() > 0 ) {
        StatusBar.setError(
            { responseText: 'Caricamento immagini in corso, attendi qualche istante e riprova.' },
            'error'
        );
        return;
    }

    // clean the title in case the user hit Ctrl+S without blurring the field first
    cleanTitleField();

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

    // CTRL+K or Command+K (Mac) — Link article
    if( (event.ctrlKey || event.metaKey) && (event.key === 'l' || event.key === 'L') ) {

        event.preventDefault();
        $('[data-cke-tooltip-text="Inserisci link ad articolo (Ctrl+L)"]').first().trigger('click');
        return false;
    }

    return true;
});
