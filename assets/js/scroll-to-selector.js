//import $ from 'jquery';

jQuery(document).on('click', '.tli-scroll-to', function(event){

    event.preventDefault();

    let selectorToScrollTo = jQuery(this).attr('href');
    jQuery(selectorToScrollTo).get(0).scrollIntoView({behavior: 'smooth'});
});
