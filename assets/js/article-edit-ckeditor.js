/**
 * This configuration was generated using the CKEditor 5 Builder. You can modify it anytime using this link:
 * https://ckeditor.com/ckeditor-5/builder/#installation/NodgNARATAdCMAYKQIwgCxSugrCFAHAGxEI44EDM1RBU+B6+lCKhGBKl6CBSkASwAuyBGGAowksdKkBdSACMUAE0UBOThDlA
 */

import {
    ClassicEditor,
    AutoLink,
    Autosave,
    BalloonToolbar,
    BlockQuote,
    Bold,
    Code,
    CodeBlock,
    Essentials,
    FindAndReplace,
    Fullscreen,
    Heading,
    HorizontalLine,
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
            'heading',
            '|',
            'bold',
            'italic',
            'strikethrough',
            'code',
            'removeFormat',
            '|',
            'horizontalLine',
            'link',
            'blockQuote',
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
        BlockQuote,
        Bold,
        Code,
        CodeBlock,
        Essentials,
        FindAndReplace,
        Fullscreen,
        Heading,
        HorizontalLine,
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
    heading: {
        options: [
            {
                model: 'paragraph',
                title: 'Paragraph',
                class: 'ck-heading_paragraph'
            },
            {
                model: 'heading1',
                view: 'h1',
                title: 'Heading 1',
                class: 'ck-heading_heading1'
            },
            {
                model: 'heading2',
                view: 'h2',
                title: 'Heading 2',
                class: 'ck-heading_heading2'
            },
            {
                model: 'heading3',
                view: 'h3',
                title: 'Heading 3',
                class: 'ck-heading_heading3'
            },
            {
                model: 'heading4',
                view: 'h4',
                title: 'Heading 4',
                class: 'ck-heading_heading4'
            },
            {
                model: 'heading5',
                view: 'h5',
                title: 'Heading 5',
                class: 'ck-heading_heading5'
            },
            {
                model: 'heading6',
                view: 'h6',
                title: 'Heading 6',
                class: 'ck-heading_heading6'
            }
        ]
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
