//import $ from 'jquery';

jQuery(document).on('submit', 'form.submit-to-path', function(event) {

    event.preventDefault();

    let form    = jQuery(this);
    let input   = form.find('input.submit-to-path');
    let slug    = input.val().trim();
    input.val(slug);

    // Trigger browser validation
    if( !this.checkValidity() ) {
        this.reportValidity();
        return false;
    }

    let url = form.attr('action');

    if( !url.endsWith('/') ) {
        url += '/';
    }

    url += encodeURIComponent(slug);

    window.location.href = url;
});
