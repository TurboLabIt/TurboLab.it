//import $ from 'jquery';
import 'jquery-is-in-viewport';

jQuery('.post-comments-list').isInViewport(function (status) {

    let isLoaded    = $(this).data('is-loaded');
    let url         = $(this).data('comments-loading-url');

    if( status !== 'entered' || isLoaded || url == '') {
        return false;
    }

    $(this).data('is-loaded', true);

    let that = $(this);

    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'html',
        success: function(data) {
            that.html(data);
        },
        error: function(jqXHR, textStatus, errorThrown) {

            that.addClass('alert alert-danger tli-fullpage-message');

            let errorMessage = jqXHR.responseText ?? null;
            if( errorMessage && errorMessage != '' ) {

                that.html(errorMessage);
                return false;
            }

            if( textStatus && textStatus != '' ) {

                that.html(textStatus);
                return false;
            }

            if( errorThrown && errorThrown != '' ) {
                that.html(errorThrown);
            }
        }
    });
});
