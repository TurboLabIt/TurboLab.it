/**
 * Usage
 * =====
 * import ArticleMeta from './article-edit-meta';
 * ArticleMeta.update(json);
 */

const articleMetaStrip = jQuery('#tli-article-meta-strip');
const articleMetaAuthorsBio = jQuery('#tli-article-authors-bio');

const ArticleMeta = {
    update(json) {
        articleMetaStrip.html(json.metaStrip);
        articleMetaAuthorsBio.html(json.metaBios);
        return this;
    }
};

export default ArticleMeta;
