//import $ from 'jquery';

$(document).on('click', '.tli-scroll-to', function(event){

    event.preventDefault();

    let selectorToScrollTo = $(this).attr('href');
    $(selectorToScrollTo).get(0).scrollIntoView({behavior: 'smooth'});
});
