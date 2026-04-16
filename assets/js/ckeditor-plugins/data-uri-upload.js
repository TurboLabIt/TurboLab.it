import { Plugin } from 'ckeditor5';
import StatusBar from '../article-edit-statusbar';


// modelElement -> { overlay, xhr }
const pending = new Map();

let scrollListenerRegistered = false;


export default class TliDataUriUpload extends Plugin {
    static get pluginName() { return 'TliDataUriUpload'; }

    init() {
        const editor = this.editor;

        // Exposed so the save flow can block while uploads are in flight
        window.TLI_PENDING_IMAGE_UPLOADS = () => pending.size;

        editor.model.document.on('change:data', () => {
            // Defer one frame so CKEditor has rendered the figure before we measure it
            requestAnimationFrame(() => scanAndProcess(editor));
        });

        if( !scrollListenerRegistered ) {
            const reposition = () => repositionAll(editor);
            window.addEventListener('scroll', reposition, { passive: true });
            window.addEventListener('resize', reposition, { passive: true });
            scrollListenerRegistered = true;
        }
    }
}


function scanAndProcess(editor) {
    const root = editor.model.document.getRoot();

    for( const value of editor.model.createRangeIn(root) ) {

        const item = value.item;
        if( !item || !item.is || !item.is('element', 'imageBlock') ) {
            continue;
        }

        const src = item.getAttribute('src') || '';
        if( !src.startsWith('data:') ) {
            continue;
        }

        if( pending.has(item) ) {
            continue;
        }

        processOne(editor, item, src);
    }
}


function processOne(editor, modelElement, dataUri) {
    const file = dataUriToFile(dataUri);
    if( !file ) {
        // malformed data URI — leave as-is, nothing to upload
        return;
    }

    const figure = getFigureDom(editor, modelElement);
    const overlay = figure ? createOverlay(figure) : null;

    const uploadUrl = jQuery('#tli-article-editor-image-gallery').data('save-url');
    const formData = new FormData();
    formData.append('images[]', file);

    const xhr = jQuery.ajax({
        url: uploadUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success(htmlResponse) {

            const newSrc = jQuery(htmlResponse).find('img').first().attr('src');

            if( !newSrc ) {
                StatusBar.setError(
                    { responseText: 'Errore interno: URL mancante nella risposta del server' },
                    'error'
                );
                cleanup(modelElement);
                return;
            }

            // modelElement may have been removed from the document (e.g. user hit undo / delete during upload)
            if( modelElement.parent !== null ) {
                editor.model.change(writer => writer.setAttribute('src', newSrc, modelElement));
            }

            appendToGallery(htmlResponse);
            cleanup(modelElement);
        },
        error(jqXHR, textStatus) {
            const message = jqXHR.responseText || 'Caricamento immagine non riuscito';
            StatusBar.setError({ responseText: message }, textStatus);
            cleanup(modelElement);
        }
    });

    pending.set(modelElement, { overlay, xhr });
}


function cleanup(modelElement) {
    const entry = pending.get(modelElement);
    if( entry && entry.overlay ) {
        entry.overlay.remove();
    }
    pending.delete(modelElement);
}


function getFigureDom(editor, modelElement) {
    const viewElement = editor.editing.mapper.toViewElement(modelElement);
    if( !viewElement ) return null;
    return editor.editing.view.domConverter.mapViewToDom(viewElement);
}


// Mirrors the overlay style used by watermark.js: fixed, 70% white, Bootstrap spinner, z-index 9999
function createOverlay(figure) {
    const rect = figure.getBoundingClientRect();
    const overlay = document.createElement('div');
    overlay.style.cssText =
        `position:fixed;top:${rect.top}px;left:${rect.left}px;` +
        `width:${rect.width}px;height:${rect.height}px;` +
        'background:rgba(255,255,255,0.7);display:flex;align-items:center;justify-content:center;z-index:9999';
    overlay.innerHTML = '<div class="spinner-border text-primary" role="status"></div>';
    document.body.appendChild(overlay);
    return overlay;
}


function repositionAll(editor) {
    for( const [modelElement, entry] of pending ) {

        if( !entry.overlay ) continue;

        const figure = getFigureDom(editor, modelElement);
        if( !figure ) continue;

        const rect = figure.getBoundingClientRect();
        entry.overlay.style.top      = rect.top + 'px';
        entry.overlay.style.left     = rect.left + 'px';
        entry.overlay.style.width    = rect.width + 'px';
        entry.overlay.style.height   = rect.height + 'px';
    }
}


function dataUriToFile(dataUri) {
    const match = dataUri.match(/^data:([^;,]+)(;base64)?,(.*)$/);
    if( !match ) return null;

    const mime      = match[1] || 'image/jpeg';
    const isBase64  = !!match[2];
    const payload   = match[3];

    let bytes;
    try {
        if( isBase64 ) {
            const bin = atob(payload);
            bytes = new Uint8Array(bin.length);
            for( let i = 0; i < bin.length; i++ ) bytes[i] = bin.charCodeAt(i);
        } else {
            const decoded = decodeURIComponent(payload);
            bytes = new Uint8Array(decoded.length);
            for( let i = 0; i < decoded.length; i++ ) bytes[i] = decoded.charCodeAt(i);
        }
    } catch(e) {
        return null;
    }

    const ext       = mimeToExtension(mime);
    const filename  = `pasted-${Date.now()}-${Math.floor(Math.random() * 1e6)}.${ext}`;
    return new File([bytes], filename, { type: mime });
}


function mimeToExtension(mime) {
    const map = {
        'image/jpeg':       'jpg',
        'image/jpg':        'jpg',
        'image/png':        'png',
        'image/gif':        'gif',
        'image/webp':       'webp',
        'image/avif':       'avif',
        'image/bmp':        'bmp',
        'image/svg+xml':    'svg'
    };
    return map[mime.toLowerCase()] || 'jpg';
}


// Mirrors the success path of article-edit-images-gallery.js so the sidebar reflects new uploads
function appendToGallery(htmlResponse) {
    jQuery('#tli-images-gallery').append(htmlResponse);

    const editorImageGallery = jQuery('#tli-article-editor-image-gallery');
    editorImageGallery.find('.tli-no-images-guide').fadeOut('slow', function() {
        editorImageGallery.find('.tli-images-guide').fadeIn('fast');
    });
}
