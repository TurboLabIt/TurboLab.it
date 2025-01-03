//import $ from 'jquery';

jQuery(document).on('submit', 'form.submit-to-path', function(event) {

    event.preventDefault();

    let form = jQuery(this);

    // Trigger browser validation
    if( !this.checkValidity() ) {
        this.reportValidity();
        return false;
    }

    let url = form.attr('action');

    if( !url.endsWith('/') ) {
        url += '/';
    }

    let slug = form.find('input.submit-to-path').val();
    url += encodeURIComponent(slug);
debugger;
    window.location.href = url;
});
