import ArticleContentEditable from './article-edit-contenteditable';
import openAjaxModal from './modal-ajax';


const ArticleAdvise = {
    run() {
        runAdvise();
    }
};

export default ArticleAdvise;

// --------------- //

function runAdvise()
{
    // the check runs server-side on the saved article: verifying a stale copy would mislead
    if( ArticleContentEditable.hasUnsavedChanges() ) {

        alert("⚠️ Ci sono modifiche non salvate.\n\nSalva l'articolo (Ctrl+S), poi avvia la verifica.");
        return;
    }

    openAjaxModal( jQuery('article').attr('data-advise-url') );
}
