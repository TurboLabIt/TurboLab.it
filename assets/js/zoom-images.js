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

    // Click on article body image → open lightbox at that image
    $(document).on('click', '#tli-article-body img', function(e) {

        e.preventDefault();

        const src     = $(this).attr('src');
        const idMatch = src.match(/-(\d+)\.[^.]+$/);
        if(!idMatch) return;

        const imageId = idMatch[1];
        let slideIndex = 0;

        items.forEach(function(item, i) {
            if(item.dataset.imageId === imageId) slideIndex = i;
        });

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
