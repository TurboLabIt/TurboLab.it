import 'slick-carousel/slick/slick.min.js';
import 'slick-carousel/slick/slick.css';
import 'slick-carousel/slick/slick-theme.css';
import '../styles/slider-custom-arrows.css';


jQuery('.tli-slick-slider').slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    dots: true,
    infinite: false,
    autoplay: false,
    //autoplaySpeed: 3000,
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
                slidesToShow: 1
            },
        },
        {
            breakpoint: 576,
            settings: {
                arrows: false,
                slidesToShow: 1,
            }
        }
    ]
});
