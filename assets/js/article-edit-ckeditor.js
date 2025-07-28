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
    HeadingButtonsUI,
    ImageBlock,
    ImageToolbar,
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


const LICENSE_KEY = $('#tli-article-body').data('ckeditor-license-key');

const editorConfig = {
    toolbar: {
        items: [
            'save', '|',
            'heading2', 'paragraph', '|',
            'bold', 'italic', 'code', 'strikethrough', '|',
            'removeFormat', '|',
            'link', '|',
            'codeBlock', 'bulletedList', 'numberedList', '|',
            'undo', 'redo', '|',
            'findAndReplace', 'fullscreen'
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
        HeadingButtonsUI,
        ImageBlock,
        ImageToolbar,
        Italic,
        Link,
        List,
        Paragraph,
        ParagraphButtonUI,
        PasteFromOffice,
        RemoveFormat,
        Strikethrough,
        // ---- TLI plugins ---- \\
        TliSavePlugin
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


        editor.keystrokes.set( 'Ctrl+M', 'code' );

        const codeButtonView = editor.ui.view.toolbar.items.find(
            item => item.label === 'Codice'
        );

        if ( codeButtonView ) {

            let codeIcon =
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-diamond-fill" viewBox="0 0 16 16">\n' +
                    '<path d="M9.05.435c-.58-.58-1.52-.58-2.1 0L4.047 3.339 8 7.293l3.954-3.954L9.049.435zm3.61 3.611L8.708 8l3.954 3.954 2.904-2.905c.58-.58.58-1.519 0-2.098l-2.904-2.905zm-.706 8.614L8 8.708l-3.954 3.954 2.905 2.904c.58.58 1.519.58 2.098 0l2.905-2.904zm-8.614-.706L7.292 8 3.339 4.046.435 6.951c-.58.58-.58 1.519 0 2.098z"/>\n' +
                '</svg>';

            codeButtonView.set({
                icon: codeIcon,
                tooltip: 'Istruzioni (Ctrl+M)'
            });
        }
    });


