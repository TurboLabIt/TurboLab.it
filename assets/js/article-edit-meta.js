/**
 * Usage
 * =====
 * import ArticleMeta from './article-edit-meta';
 * ArticleMeta.update(json);
 */

import * as PageCounter from './page-counter';

const articleMetaStrip  = jQuery('#tli-article-meta-strip');
const articleAuthorsBio = jQuery('#tli-article-authors-bio');
const articleTags       = jQuery('#tli-article-tags');
const articleFiles      = jQuery('#tli-downloadable-files');

const ArticleMeta = {
    update(json) {
        document.title = jQuery('<textarea/>').html(json.title).val();
        history.replaceState(null, null, json.path);
        articleMetaStrip.html(json.strip);
        articleAuthorsBio.html(json.bios);
        articleTags.html(json.tags);
        articleFiles.html(json.files);
        PageCounter.odometerUpdate(json.views, '.tli-views-num-target');
        PageCounter.odometerUpdate(json.commentsNum, '.tli-comments-num-target');
        return this;
    }
};

export default ArticleMeta;
