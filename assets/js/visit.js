//import $ from 'jquery';
import Odometer from 'odometer';
import 'odometer/themes/odometer-theme-default.css';


let url     = $('body').data('tli-visit-url');
let cmsType = $('body').data('tli-cms-type');
let cmsId   = $('body').data('tli-cms-id');

if( cmsType && cmsId ) {
    $.post(url, {cmsType: cmsType, cmsId: cmsId})

        .done( function(response) {

            let views = response.views ?? null;
            let viewsTarget = $('.tli-views-num-target');
            if( views !== null && viewsTarget.length ) {

                let odometer = new Odometer({
                    el:  viewsTarget[0],
                    value: viewsTarget.data('value'),
                    format: '(.ddd)',
                    theme: 'default'
                });

                odometer.render();
                odometer.update(views);
            }


            let comments = response.comments ?? null;
            let commentsTarget = $('.tli-comments-num-target');
            if( comments !== null && commentsTarget.length ) {

                let odometer = new Odometer({
                    el:  commentsTarget[0],
                    value: commentsTarget.data('value'),
                    format: '(.ddd)',
                    theme: 'default'
                });

                odometer.render();
                odometer.update(comments);
            }
        })
}
