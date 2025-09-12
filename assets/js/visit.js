//import $ from 'jquery';

let url     = $('body').data('tli-visit-url');
let cmsType = $('body').data('tli-cms-type');
let cmsId   = $('body').data('tli-cms-id');

if( cmsType && cmsId ) {
    $.post(url, {cmsType: cmsType, cmsId: cmsId});
}
