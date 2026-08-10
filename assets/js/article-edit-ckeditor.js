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
    ImageToolbar,
    Italic,
    Link,
    List,
    Paragraph,
    ParagraphButtonUI,
    PasteFromOffice,
    RemoveFormat,
    Strikethrough,
    Table,
    TableCaption,
    TableToolbar,
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
import TliLinkArticle from "./ckeditor-plugins/link-article";
import TliLinkFile from "./ckeditor-plugins/link-file";
import TliWatermark from "./ckeditor-plugins/watermark";
import TliFormatPlugin from "./ckeditor-plugins/format";
import TliDataUriUpload from "./ckeditor-plugins/data-uri-upload";
import TliTablePasteGuard from "./ckeditor-plugins/table-paste-guard";
import TliAdvisePlugin from "./ckeditor-plugins/advise";


const $articleBody = $('#tli-article-body');
const LICENSE_KEY = $articleBody.data('ckeditor-license-key');
const ALLOW_EXTENDED_HTML = $articleBody.data('allow-extended-html') === true;

const editorConfig = {
    toolbar: {
        items: [
            'save', '|',
            'heading2', ...(ALLOW_EXTENDED_HTML ? ['heading3', 'heading4', 'heading5', 'heading6'] : []), 'paragraph', '|',
            'bold', 'italic', 'strikethrough', 'tliIstruzioni', 'tliUpdate', 'removeFormat', '|',
            'tliLinkArticle', 'tliLinkFile', 'link', 'tliyoutube', '|',
            /*'codeBlock',*/ 'bulletedList', 'numberedList',
            ...(ALLOW_EXTENDED_HTML ? ['insertTable'] : []), '|',
            'undo', 'redo', '|',
            'findAndReplace', /*'fullscreen'*/ '|',
            'tliFormat', '|',
            'tliAdvise', 'tliPublishingStatus'
        ],
        shouldNotGroupWhenFull: true
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
        ImageToolbar,
        Italic,
        Link,
        List,
        Paragraph,
        ParagraphButtonUI,
        PasteFromOffice,
        RemoveFormat,
        Strikethrough,
        ...(ALLOW_EXTENDED_HTML ? [Table, TableCaption, TableToolbar] : [TliTablePasteGuard]),
        // ---- TLI plugins ---- \\
        TliSavePlugin,
        TliUpdatePlugin,
        TliIstruzioniPlugin,
        TliPublishingStatusPlugin,
        TliYoutube,
        TliLinkArticle,
        TliLinkFile,
        TliWatermark,
        TliFormatPlugin,
        TliDataUriUpload,
        TliAdvisePlugin
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
    // Read only when the Table plugins are loaded (ALLOW_EXTENDED_HTML)
    table: {
        contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'toggleTableCaption'],
        // emit a real <caption> inside <table>. The default is <figcaption> inside <figure class="table">,
        // which the server-side allowlist doesn't have, so the caption text would end up loose in the body.
        tableCaption: { useCaptionElement: true }
    },
    // https://ckeditor.com/docs/ckeditor5/latest/features/headings.html
    image: {
        toolbar: ['tliWatermark']
    },
    heading: {
        options: [
            { model: 'heading2', view: 'h2', title: 'Titolo', class: 'ck-heading_heading2' },
            ...(ALLOW_EXTENDED_HTML
                ? [
                    { model: 'heading3', view: 'h3', title: 'Sottotitolo',    class: 'ck-heading_heading3' },
                    { model: 'heading4', view: 'h4', title: 'Sottotitolo H4', class: 'ck-heading_heading4' },
                    { model: 'heading5', view: 'h5', title: 'Sottotitolo H5', class: 'ck-heading_heading5' },
                    { model: 'heading6', view: 'h6', title: 'Sottotitolo H6', class: 'ck-heading_heading6' }
                  ]
                : []),
            { model: 'paragraph', title: 'Testo normale', class: 'ck-heading_paragraph' }
        ]
    }
};


ClassicEditor.create(document.querySelector('#tli-article-body'), editorConfig)
    .then(editor => {

        // add the "data-tli-editable-id=body" attribute to CKEditor own div
        const addAttribute = () => {
            const editableElement = editor.editing.view.getDomRoot();
            if (editableElement && editableElement.getAttribute('data-tli-editable-id') !== 'body') {
                editableElement.setAttribute('data-tli-editable-id', 'body');
            }
        };

        addAttribute();

        // Re-add after each render
        editor.editing.view.on('render', () => {
            addAttribute();
        });

        ArticleContentEditable.cacheTextHashForComparison();


        const debouncedShowWarning = debounce(() => {
            ArticleContentEditable.showWarningIfChanged();
        }, 300);

        editor.model.document.on('change:data', () => {
            debouncedShowWarning();
        });

        // Title button
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
