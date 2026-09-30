import ArticleContentEditable from './article-edit-contenteditable';
import openAjaxModal, { openAjaxModalIf } from './modal-ajax';


const ArticleAdvise = {
    // "Verifica" button: the author asked, so "all clear" is an answer too
    run() {
        runAdvise(false);
    },
    // automatic, after a publishing-status change: nobody asked, so the modal opens only if there's advice
    runQuiet() {
        runAdvise(true);
    }
};

export default ArticleAdvise;

// --------------- //

function runAdvise(quiet)
{
    // the check runs server-side on the saved article: verifying a stale copy would mislead
    if( ArticleContentEditable.hasUnsavedChanges() ) {

        alert("⚠️ Ci sono modifiche non salvate.\n\nSalva l'articolo (Ctrl+S), poi avvia la verifica.");
        return;
    }

    let endpoint = jQuery('article').attr('data-advise-url');

    if(quiet) {

        openAjaxModalIf(endpoint, json => json.adviceNum > 0);

    } else {

        openAjaxModal(endpoint);
    }
}
