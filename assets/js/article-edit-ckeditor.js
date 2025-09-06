/**
 * This configuration was generated using the CKEditor 5 Builder. You can modify it anytime using this link:
 * https://ckeditor.com/ckeditor-5/builder/#installation/NodgNARATAdCMAYKQIwgCxSugrCFAHAGxEI44EDM1RBU+B6+KAnClJUZYTltshACWAF2QIwwFGCniZ0gLqQAxgBMcAQ0wEI8oA==
 */

import {
    ClassicEditor,
    AutoLink,
    Autosave,
    BalloonToolbar,
    Bold,
    Code,
    CodeBlock,
    Essentials,
    FindAndReplace,
    Fullscreen,
    GeneralHtmlSupport,
    Heading,
    HeadingButtonsUI,
    ImageBlock,
    Italic,
    Link,
    List,
    Paragraph,
    ParagraphButtonUI,
    PasteFromOffice,
    RemoveFormat,
    Strikethrough,
    // required for custom plugins
    Plugin
} from 'ckeditor5';

import translations from 'ckeditor5/translations/it.js';
import 'ckeditor5/ckeditor5.css';

// ---- TLI plugins ---- \\
import debounce from './debouncer';
import ArticleContentEditable from './article-edit-contenteditable';
import TliSavePlugin from "./ckeditor-plugins/save";
import TliUpdatePlugin from "./ckeditor-plugins/update";
import TliIstruzioniPlugin from "./ckeditor-plugins/istruzioni";
import TliPublishingStatusPlugin from "./ckeditor-plugins/publishing-status";
import TliYoutube from "./ckeditor-plugins/youtube";


const LICENSE_KEY = $('#tli-article-body').data('ckeditor-license-key');

const editorConfig = {
    toolbar: {
        items: [
            'save', '|',
            'heading2', 'paragraph', '|',
            'bold', 'italic', 'strikethrough', 'tliIstruzioni', 'tliUpdate', 'removeFormat', '|',
            'link', 'tliyoutube', '|',
            /*'codeBlock',*/ 'bulletedList', 'numberedList', '|',
            'undo', 'redo', '|',
            'findAndReplace', /*'fullscreen'*/
            'tliPublishingStatus'
        ],
        shouldNotGroupWhenFull: false
    },
    plugins: [
        AutoLink,
        Autosave,
        BalloonToolbar,
        Bold,
        Code,
        CodeBlock,
        Essentials,
        FindAndReplace,
        Fullscreen,
        GeneralHtmlSupport,
        Heading,
        HeadingButtonsUI,
        ImageBlock,
        Italic,
        Link,
        List,
        Paragraph,
        ParagraphButtonUI,
        PasteFromOffice,
        RemoveFormat,
        Strikethrough,
        // ---- TLI plugins ---- \\
        TliSavePlugin,
        TliUpdatePlugin,
        TliIstruzioniPlugin,
        TliPublishingStatusPlugin,
        TliYoutube
    ],
    balloonToolbar: ['tliIstruzioni', '|', 'bold', 'italic',  'removeFormat', '|', 'link'],
    fullscreen: {
        onEnterCallback: container =>
            container.classList.add(
                'editor-container',
                'editor-container_classic-editor',
                'editor-container_include-fullscreen',
                'main-container'
            )
    },
    language: 'it',
    licenseKey: LICENSE_KEY,
    link: {
        addTargetToExternalLinks: true,
        defaultProtocol: 'https://'
    },
    placeholder: 'Digita qui il testo del tuo articolo',
    translations: [translations],
    // https://ckeditor.com/docs/ckeditor5/latest/features/headings.html
    heading: {
        options: [
            { model: 'heading2', view: 'h2', title: 'Titolo', class: 'ck-heading_heading2' },
            { model: 'paragraph', title: 'Testo normale', class: 'ck-heading_paragraph' }
        ]
    }
};


let ckeditorBodySelector = '.ck.ck-editor__main [contenteditable="true"]';

$(document).on('click', ckeditorBodySelector, function () {
    $(this).attr('data-tli-editable-id', 'body');
});


$(document).on('blur', ckeditorBodySelector, function () {
    $(this).attr('data-tli-editable-id', 'body');
});


ClassicEditor.create(document.querySelector('#tli-article-body'), editorConfig)
    .then(editor => {

        $('#tli-article-body').removeAttr('data-tli-editable-id');

        let ckeditorBody = $(ckeditorBodySelector);
        ckeditorBody.attr('data-tli-editable-id', 'body');

        ArticleContentEditable.cacheTextHashForComparison();


        const debouncedShowWarning = debounce(() => {
            ArticleContentEditable.showWarningIfChanged();
        }, 300);

        editor.model.document.on('change:data', () => {

            ckeditorBody.attr('data-tli-editable-id', 'body');
            debouncedShowWarning();
        });

        // Title
        const titleButtonView = editor.ui.view.toolbar.items.find(
            item => item.label === 'Titolo'
        );

        titleButtonView.set({
            icon: $('#tli-toolbar-icons .bi-textarea-t')[0].outerHTML,
            tooltip: 'Titolo (Ctrl+1)'
        });


        $(document).on('click', '#tli-images-gallery img', function(e) {

            e.preventDefault();
            editor.execute('insertImage', { source: $(this).attr('src') });
            editor.editing.view.focus();
        });
    });
