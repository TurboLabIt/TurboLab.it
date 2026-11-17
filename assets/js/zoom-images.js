const lightboxEl = document.getElementById('tli-image-lightbox');

if(lightboxEl) {

    const carouselEl = document.getElementById('tli-image-lightbox-carousel');
    const items      = carouselEl.querySelectorAll('.carousel-item');
    const thumbs     = lightboxEl.querySelectorAll('.tli-lightbox-thumb');
    let carousel     = null;

    function getCarousel() {
        if(!carousel) {
            carousel = new bootstrap.Carousel(carouselEl, { interval: false });
        }
        return carousel;
    }

    function loadImage(index) {

        if(index < 0 || index >= items.length) return;

        const img = items[index].querySelector('img');
        if(img && img.dataset.src && !img.src) {
            img.src = img.dataset.src;
        }
    }

    function loadAllThumbnails() {
        thumbs.forEach(function(thumb) {
            if(thumb.dataset.src && !thumb.src) {
                thumb.src = thumb.dataset.src;
            }
        });
    }

    // Click on one of the article's own images → open lightbox at that image.
    // Any other image (an ad, an outside picture in an old body) keeps its normal click.
    $(document).on('click', '#tli-article-body img', function(e) {

        const idMatch = ($(this).attr('src') || '').match(/-(\d+)\.[^.]+$/);
        if(!idMatch) return;

        const slideIndex = Array.from(items).findIndex(item => item.dataset.imageId === idMatch[1]);
        if(slideIndex === -1) return;

        e.preventDefault();

        loadImage(slideIndex);
        loadImage(slideIndex - 1);
        loadImage(slideIndex + 1);
        loadAllThumbnails();

        getCarousel().to(slideIndex);
        updateActiveThumbnail(slideIndex);
        new bootstrap.Modal(lightboxEl).show();
    });

    // Sync active thumbnail + preload neighbors on slide
    carouselEl.addEventListener('slid.bs.carousel', function(e) {
        updateActiveThumbnail(e.to);
        loadImage(e.to);
        loadImage(e.to - 1);
        loadImage(e.to + 1);
    });

    function updateActiveThumbnail(index) {
        thumbs.forEach(function(t, i) { t.classList.toggle('active', i === index); });
    }
}
