export function fastHash16(str)
{
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = (hash * 31 + str.charCodeAt(i)) | 0;
    }
    return ('00000000' + (hash >>> 0).toString(16)).slice(-8).repeat(2);
}


export function fastHash16ElementHtml(element)
{
    let value = jQuery(element).html().replace(/&nbsp;/g, ' ').trim();

    if( value == '' ) {
        return '';
    }

    return fastHash16(value);
}
