import SparkMD5 from 'spark-md5';


/**
 * What a Download is made from (docs/downloadable-files.md): a file or a URL. Shared by "Aggiungi un Download"
 * (ckeditor-plugins/link-file.js) and "Sostituisci" (files-edit.js)
 */

export function isFileDrag(event) {
    const dataTransfer = (event.originalEvent || event).dataTransfer;
    return !!dataTransfer && Array.from(dataTransfer.types || []).includes('Files');
}


export const URL_REGEX = /^(https?:\/\/|magnet:|ed2k:)\S+$/i;

// the host says more than the extension: an app store link has none
export const FORMAT_BY_HOST = [
    [/(^|\.)play\.google\.com$/,                    'google play (app store)'],
    [/(^|\.)(apps|www)\.microsoft\.com$/,           'microsoft store (app store)'],
    [/(^|\.)f-droid\.org$/,                         'f-droid (app store)'],
    [/(^|\.)chrome(webstore)?\.google\.com$/,       'chrome web store']
];


// the title of an uploaded file is also the name it's downloaded with (the server appends the extension)
export function titleFromFileName(fileName) {
    return fileName.replace(/\.[^.]+$/, '') || fileName;
}


// the file a URL names: a magnet's "dn", an ed2k link's name, or a last path segment with an extension. Else ''
export function fileNameFromUrl(rawUrl) {

    const ed2k = /^ed2k:\/\/\|file\|([^|]+)\|/i.exec(rawUrl);
    if( ed2k ) {
        return safeDecode( ed2k[1] );
    }

    try {
        const url = new URL(rawUrl);

        if( url.protocol == 'magnet:' ) {
            return url.searchParams.get('dn') || '';
        }

        const lastSegment = safeDecode( url.pathname.split('/').filter(Boolean).pop() || '' );
        return /\.[a-z0-9]{1,5}$/i.test(lastSegment) ? lastSegment : '';

    } catch(e) {
        return '';
    }
}


export function safeDecode(text) {
    try {
        return decodeURIComponent(text);
    } catch(e) {
        return text;
    }
}


// a readable title, only when the URL names a file: without extension and separators.
// Anything else ("details", "latest", "download"...) would be noise: the author writes it
export function titleFromUrl(rawUrl) {
    return fileNameFromUrl(rawUrl).replace(/\.[a-z0-9]{1,5}$/i, '').replace(/[-_.+]+/g, ' ').trim();
}


export function guessFormatFromUrl(rawUrl) {

    try {
        const hostname = new URL(rawUrl).hostname;
        for( const [regex, format] of FORMAT_BY_HOST ) {
            if( hostname && regex.test(hostname) ) {
                return format;
            }
        }

    } catch(e) {}

    const extension = /\.([a-z0-9]{1,10})$/i.exec( fileNameFromUrl(rawUrl) );
    return extension ? extension[1].toLowerCase() : null;
}


// same hash the server stores (file.hash), computed in chunks: big files don't freeze the page
export async function md5OfFile(file, onProgress) {

    const chunkSize = 4 * 1024 * 1024;
    const spark     = new SparkMD5.ArrayBuffer();

    for( let offset = 0; offset < file.size; offset += chunkSize ) {
        spark.append( await file.slice(offset, offset + chunkSize).arrayBuffer() );
        onProgress( Math.min(100, Math.round( (offset + chunkSize) / file.size * 100 )) );
    }

    return spark.end();
}


export function sizeLabel(bytes) {
    const units = ['bytes', 'kB', 'MB', 'GB'];
    let i = 0;
    while( bytes >= 1024 && i < units.length - 1 ) { bytes /= 1024; i++; }
    return (i == 0 ? bytes : bytes.toFixed(1)) + ' ' + units[i];
}
