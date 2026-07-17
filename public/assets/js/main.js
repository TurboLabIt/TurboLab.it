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



//===== Slick sliders now live in assets/js/slider.js (Webpack/yarn), initialized on .tli-slick-slider



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


jQuery('#tli-youtube-video-player iframe').on('load', function(){

    let videoPlayer = jQuery(this);

    let noAutoplay = videoPlayer.data('tli-autoplay');
    if( noAutoplay == 0 ) {
        return false;
    }

    let playCommandTarget = videoPlayer[0].contentWindow;
    playCommandTarget.postMessage('{"event":"command","func":"playVideo","args":""}', '*');
});


jQuery(document).on('click', '.tli-video-thumb', function(){

    let clickedVideoEmbedUrl    = jQuery(this).data('embed-url');
    let clickedVideoTitle       = jQuery(this).find('.title').html();

    let videoPlayerContainer    = jQuery('#tli-youtube-video-player');
    let videoPlayer             = videoPlayerContainer.find('iframe');

    videoPlayer.data('tli-autoplay', 1);
    videoPlayer.attr('src', clickedVideoEmbedUrl);
    videoPlayerContainer.find('.title').html(clickedVideoTitle);
});
