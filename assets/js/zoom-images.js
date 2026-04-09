const lightboxEl = document.getElementById('tli-image-lightbox');

if(lightboxEl) {

    const carouselEl = document.getElementById('tli-image-lightbox-carousel');
    const carousel   = new bootstrap.Carousel(carouselEl, { interval: false });
    const thumbs     = lightboxEl.querySelectorAll('.tli-lightbox-thumb');

    // Click on article body image → open lightbox at that image
    $(document).on('click', '#tli-article-body img', function(e) {

        e.preventDefault();

        const src     = $(this).attr('src');
        const idMatch = src.match(/-(\d+)\.[^.]+$/);
        if(!idMatch) return;

        const imageId = idMatch[1];
        const items   = carouselEl.querySelectorAll('.carousel-item');
        let slideIndex = 0;

        items.forEach((item, i) => {
            if(item.dataset.imageId === imageId) slideIndex = i;
        });

        carousel.to(slideIndex);
        updateActiveThumbnail(slideIndex);
        new bootstrap.Modal(lightboxEl).show();
    });

    // Sync active thumbnail on carousel slide
    carouselEl.addEventListener('slid.bs.carousel', function(e) {
        updateActiveThumbnail(e.to);
    });

    function updateActiveThumbnail(index) {
        thumbs.forEach((t, i) => t.classList.toggle('active', i === index));
    }
}
