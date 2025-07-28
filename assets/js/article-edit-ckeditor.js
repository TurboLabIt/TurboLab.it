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
    Heading,
    ImageBlock,
    ImageToolbar,
    Italic,
    Link,
    List,
    Paragraph,
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
import TliH2Plugin from "./ckeditor-plugins/h2";


const LICENSE_KEY = $('#tli-article-body').data('ckeditor-license-key');

const editorConfig = {
    toolbar: {
        items: [
            'save',
            'h2',
            //'heading',
            '|',
            'undo',
            'redo',
            '|',
            'findAndReplace',
            'fullscreen',
            '|',
            'bold',
            'italic',
            'strikethrough',
            'code',
            'removeFormat',
            '|',
            'link',
            'codeBlock',
            '|',
            'bulletedList',
            'numberedList'
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
        Heading,
        ImageBlock,
        ImageToolbar,
        Italic,
        Link,
        List,
        Paragraph,
        PasteFromOffice,
        RemoveFormat,
        Strikethrough,
        // ---- TLI plugins ---- \\
        TliSavePlugin, TliH2Plugin
    ],
    balloonToolbar: ['bold', 'italic', '|', 'link', '|', 'bulletedList', 'numberedList'],
    fullscreen: {
        onEnterCallback: container =>
            container.classList.add(
                'editor-container',
                'editor-container_classic-editor',
                'editor-container_include-fullscreen',
                'main-container'
            )
    },
    image: {
        toolbar: []
    },
    language: 'it',
    licenseKey: LICENSE_KEY,
    link: {
        addTargetToExternalLinks: true,
        defaultProtocol: 'https://',
        decorators: {
            toggleDownloadable: {
                mode: 'manual',
                label: 'Downloadable',
                attributes: {
                    download: 'file'
                }
            }
        }
    },
    placeholder: 'Digita qui il testo del tuo articolo',
    translations: [translations]
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
    });


