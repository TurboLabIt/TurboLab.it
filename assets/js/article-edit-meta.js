/**
 * Usage
 * =====
 * import ArticleMeta from './article-edit-meta';
 * ArticleMeta.update(json);
 */

const articleMetaStrip  = jQuery('#tli-article-meta-strip');
const articleAuthorsBio = jQuery('#tli-article-authors-bio');
const articleTags       = jQuery('#tli-article-tags');

const ArticleMeta = {
    update(json) {
        articleMetaStrip.html(json.strip);
        articleAuthorsBio.html(json.bios);
        articleTags.html(json.tags);
        return this;
    }
};

export default ArticleMeta;
