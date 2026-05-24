//import $ from 'jquery';
import StatusBar from './article-edit-statusbar';
import ArticleMeta from "./article-edit-meta";


const ArticlePublishable = {
    setPublishingStatus(status, onSuccessCallback) {
        setPublishingStatus(status, onSuccessCallback);
    },
    deleteArticle() {
        deleteArticle();
    }
};

export default ArticlePublishable;


$(document).on('click', '.tli-publishing-status-ready', (event) => {

    event.preventDefault();

    if( !confirm("Sei sicuro che l'articolo sia davvero pronto?") ) {
        return false;
    }

    // ✅ Pronto e finito (visibile al pubblico)
    setPublishingStatus(3);
})


// --------------- //

var articlePublishingRequest = null;
function setPublishingStatus(status, onSuccessCallback)
{
    StatusBar.setSaving();

    if( articlePublishingRequest != null ) {
        articlePublishingRequest.abort();
    }

    let article = jQuery('article');
    let endpoint= article.attr('data-set-publishing-status-url');
    let payload = {
        "status"    : status
    };

    articlePublishingRequest =
        jQuery.post(endpoint, payload, function(json) {

            StatusBar.setSaved(json.message);
            ArticleMeta.update(json);
            onSuccessCallback();

        }, 'json')

            .fail(function(jqXHR, responseText) {
                StatusBar.setError(jqXHR, responseText);
            });
}


var articleDeleteRequest = null;
function deleteArticle()
{
    let confirmMessage =
        "Eliminazione completa dell'articolo.\n\n" +
        "Questa operazione è IRREVERSIBILE: l'articolo verrà eliminato definitivamente e non potrà essere recuperato.\n\n" +
        "⚠️ Procedere?";

    if( !confirm(confirmMessage) ) {
        return false;
    }

    let article     = jQuery('article');
    let endpoint    = article.attr('data-delete-url');

    if( !endpoint ) {
        return false;
    }

    StatusBar.setSaving();

    if( articleDeleteRequest != null ) {
        articleDeleteRequest.abort();
    }

    articleDeleteRequest =
        jQuery.post(endpoint, {}, function(json) {

            StatusBar.setSaved(json.message);

        }, 'json')

            .fail(function(jqXHR, responseText) {
                StatusBar.setError(jqXHR, responseText);
            })

            .always(function() {
                jQuery('body').addClass('tli-article-deleted');
            });
}
