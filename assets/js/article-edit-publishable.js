//import $ from 'jquery';
import StatusBar from './article-edit-statusbar';
import ArticleMeta from "./article-edit-meta";


const ArticlePublishable = {
    setPublishingStatus(status, onSuccessCallback) {
        setPublishingStatus(status, onSuccessCallback);
    }
};

export default ArticlePublishable;


$(document).on('click', '.tli-publishing-status-ready', (event) => {

    event.preventDefault();

    if( !confirm("Sei sicuro che l'articolo sia davvero pronto?") ) {
        return false;
    }

    $('.ck.ck-editor button.ck-button').filter(function() {
            return /pronto.*finito/i.test($(this).text());
        }).trigger('click');
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

                StatusBar
                    .setError(jqXHR, responseText);
            });
}
