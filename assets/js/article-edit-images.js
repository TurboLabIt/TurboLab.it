//import $ from 'jquery';


jQuery(document).on('click', '.tli-image-upload',  function(event) {
    $(this).siblings('input[type="file"]').click();
});
