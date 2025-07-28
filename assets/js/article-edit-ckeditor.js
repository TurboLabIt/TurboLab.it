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
    ImageBlock,
    ImageToolbar,
    Italic,
    Link,
    List,
    Paragraph,
    PasteFromOffice,
    RemoveFormat,
    Strikethrough
} from 'ckeditor5';

import translations from 'ckeditor5/translations/it.js';

import 'ckeditor5/ckeditor5.css';

const LICENSE_KEY = $('#tli-article-body').data('ckeditor-license-key');

const editorConfig = {
    toolbar: {
        items: [
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
        ImageBlock,
        ImageToolbar,
        Italic,
        Link,
        List,
        Paragraph,
        PasteFromOffice,
        RemoveFormat,
        Strikethrough
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
    placeholder: 'Type or paste your content here!',
    translations: [translations]
};

ClassicEditor.create(document.querySelector('#tli-article-body'), editorConfig);
