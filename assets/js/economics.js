const TLI_ECONOMICS_HIDE_COOKIE_NAME    = 'tli-economics-2025';
const TLI_AXX_SPACE_SELECTOR            = 'ins.adsbygoogle';
const TLI_HANDSTOP_SELECTOR             = '.tli-handstop';


$(document).on('click', '.economics-hide-handstop', function(event){

    event.preventDefault();
    $(TLI_HANDSTOP_SELECTOR).fadeOut();
    Cookies.set(TLI_ECONOMICS_HIDE_COOKIE_NAME, '1', {expires: 3, path: '/', sameSite: 'lax', secure: true});
});


$(window).on('load', function() {

    if(
        !window.tliExpectAxx ||
        Cookies.get(TLI_ECONOMICS_HIDE_COOKIE_NAME) == 1 ||
        $('#cl-consent').length != 0
    ) {
        return false;
    }

    setTimeout(function() {

        if( $(TLI_AXX_SPACE_SELECTOR).length > 0 ) {
            return false;
        }

        //display warn
        $(TLI_HANDSTOP_SELECTOR).fadeIn(2000);

    }, 2000);
});
