/**
 * Usage
 * =====
 * import StatusBar from './article-edit-statusbar';
 * StatusBar.show();
 */

const articleSavingStatusBar = jQuery('#tli-article-saving-status-bar');

const StatusBar = {
    setUnsaved() {
        this.show();
        setArticleSavingStatusBar('alert-danger', 1, 0, 0);
        return this;
    },
    setSaving() {
        this.show();
        setArticleSavingStatusBar('alert-primary', 0, 1, 0);
        return this;
    },
    setSaved(responseText) {
        this.show();
        setArticleSavingStatusBar('alert-success', 0, 0, responseText);
        return this;
    },
    setSavedIfNotFurtherEdited(responseText) {
        let unsavedWarning = articleSavingStatusBar.find('.tli-warning-unsaved');
        if( !unsavedWarning.is(':visible') ) {
            this.setSaved(responseText);
        }
        return this;
    },
    setError(jqXHR, responseText) {
        if(responseText != 'abort') {
            this.show();
            setArticleSavingStatusBar('alert-danger', 0, 0, jqXHR.responseText);
        }
        return this;
    },
    showTrySaveAgain() {
        this.show();
        articleSavingStatusBar.find('.tli-action-try-again').removeClass('collapse');
        return this;
    },
    show() {
        articleSavingStatusBar.removeClass('collapse');
        return this;
    },
    hide() {
        articleSavingStatusBar.addClass('collapse');
        return this;
    }
};

export default StatusBar;


function setArticleSavingStatusBar(alertClass, showUnsavedTextMessage, showLoaderino, responseText)
{
    let articleSavingStatusBar = jQuery('#tli-article-saving-status-bar');
    articleSavingStatusBar.find('.tli-action-try-again').addClass('collapse');

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
}
