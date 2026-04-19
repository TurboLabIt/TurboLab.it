//import $ from 'jquery';
import * as PageCounter from './page-counter';


let url     = $('body').data('tli-visit-url');
let cmsType = $('body').data('tli-cms-type');
let cmsId   = $('body').data('tli-cms-id');

if(url && cmsType && cmsId) {
    $.post(url, {cmsType: cmsType, cmsId: cmsId})
        .done( function(response) {

            PageCounter.odometerUpdate(response.views, '.tli-views-num-target');
            PageCounter.odometerUpdate(response.comments, '.tli-comments-num-target');
        });
}
