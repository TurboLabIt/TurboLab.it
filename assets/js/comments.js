//import $ from 'jquery';
import 'jquery-is-in-viewport';
import selectAndCopy from "./select-and-copy";
import Validator from './validator';


$('.post-comments-list').isInViewport(function (status) {

    let isLoaded    = $(this).data('is-loaded');
    let url         = $(this).data('comments-loading-url');

    if( status !== 'entered' || isLoaded || url == '') {
        return false;
    }

    if( !Validator.isSameOriginHttpsUrl(url) ) {
        return false;
    }

    $(this).data('is-loaded', true);

    let that = $(this);

    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'html',
        success: function(data) {
            // data is trusted phpBB-rendered HTML from the comments endpoint
            // (same-origin, validated above); phpBB handles its own escaping.
            that.html(data);
            $(".spoiler_button").css("display", "block");
        },
        error: function(jqXHR, textStatus, errorThrown) {

            that.addClass('alert alert-danger tli-fullpage-message');

            let errorMessage = jqXHR.responseText ?? null;
            if( errorMessage && errorMessage != '' ) {

                that.text(errorMessage);
                return false;
            }

            if( textStatus && textStatus != '' ) {

                that.text(textStatus);
                return false;
            }

            if( errorThrown && errorThrown != '' ) {
                that.text(errorThrown);
            }
        }
    });
});


// phpBB function call is hardcoded => this is a compat. layer
window.selectCode = async function selectCode(target)
{
    let realTarget = $(target).closest('.codebox').find('code')[0];
    selectAndCopy(realTarget);
};
