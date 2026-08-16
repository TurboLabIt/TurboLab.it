import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import dos from 'highlight.js/lib/languages/dos';
import powershell from 'highlight.js/lib/languages/powershell';
import ini from 'highlight.js/lib/languages/ini';
import xml from 'highlight.js/lib/languages/xml';
import css from 'highlight.js/lib/languages/css';
import javascript from 'highlight.js/lib/languages/javascript';
import php from 'highlight.js/lib/languages/php';
import python from 'highlight.js/lib/languages/python';
import sql from 'highlight.js/lib/languages/sql';
import json from 'highlight.js/lib/languages/json';

hljs.registerLanguage('bash', bash);
hljs.registerLanguage('dos', dos);
hljs.registerLanguage('powershell', powershell);
hljs.registerLanguage('ini', ini);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('css', css);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('php', php);
hljs.registerLanguage('python', python);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('json', json);

/**
 * Highlights the article's code blocks — only those that DECLARE a registered language: the
 * default "Testo semplice" block has no language-* class and must stay untouched, so hljs
 * autodetection is never allowed to run. An unknown declared language (server allows it, this
 * bundle doesn't register it yet) degrades to an unhighlighted block.
 *
 * When the page is in editing mode the body div belongs to CKEditor: hands off, or the editor
 * would ingest the injected hljs <span>s on its first load.
 */
$(function () {

    const $articleBody = $('#tli-article-body');
    if( $articleBody.length === 0 || $articleBody.is('[data-ckeditor-license-key]') ) {
        return;
    }

    $articleBody.find('pre code[class*="language-"]').each(function () {

        const language =
            [...this.classList]
                .find(cssClass => cssClass.startsWith('language-'))
                ?.substring('language-'.length);

        if( language && hljs.getLanguage(language) ) {
            hljs.highlightElement(this);
        }
    });
});
