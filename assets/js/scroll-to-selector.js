//import $ from 'jquery';

jQuery(document).on('click', '.tli-scroll-to', function(event){

    event.preventDefault();

    let selectorToScrollTo = $(this).data('tli-scroll-to');

    $("html, body").animate({
        scrollTop: $(selectorToScrollTo).offset().top
    }, 1000);
});
