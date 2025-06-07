// ⚠️ requires: <link rel="preload" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
// ⚠️ requires: <link rel="preload" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
// ⚠️ requires: <script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>

//import $ from 'jquery';

jQuery('.tli-slick-slider').slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    dots: false,
    infinite: true,
    autoplay: true,
    autoplaySpeed: 3000,
    arrows: true,
    prevArrow: '<span class="prev"><i class="fa fa-angle-left"></i></span>',
    nextArrow: '<span class="next"><i class="fa fa-angle-right"></i></span>',
    speed: 500,
    adaptiveHeight: true,
    responsive: [
        {
            breakpoint: 768,
            settings: {
                arrows: false,
            }
        }
    ]
});
