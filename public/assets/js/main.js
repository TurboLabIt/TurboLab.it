//===== Prealoder

jQuery(window).on('load', function (event) {
    jQuery('.preloader').delay(500).fadeOut(500);
});

//===== Sticky

jQuery(window).on('scroll', function (event) {
    var scroll = jQuery(window).scrollTop();
    if (scroll < 110) {
        jQuery(".navigation").removeClass("sticky");
    } else {
        jQuery(".navigation").addClass("sticky");
    }
});



//===== stellarnav js

jQuery(document).ready(function ($) {
    jQuery('.stellarnav').stellarNav({
        theme: 'light',
        breakpoint: 991,
        position: 'right'
    });
});


// Go to Top

// Scroll Event
$(window).on('scroll', function () {
    var scrolled = $(window).scrollTop();
    if (scrolled > 300) $('.go-top').addClass('active');
    if (scrolled < 300) $('.go-top').removeClass('active');
});

// Click Event
$('.go-top').on('click', function () {
    $("html, body").animate({
        scrollTop: "0"
    }, 1200);
});
