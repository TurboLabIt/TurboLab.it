jQuery(document).on('mouseenter', '.tli-file-download i.fa-download', function(event){
    jQuery(this).addClass('fa-bounce');
});

jQuery(document).on('mouseleave', '.tli-file-download i.fa-download', function(event){
    jQuery(this).removeClass('fa-bounce');
});


jQuery(document).on('mouseenter', '.tli-file-download .tli-download-text-link', function(event){
    jQuery(this).closest('.tli-file-download').find('i.fa-download').addClass('fa-bounce');
});

jQuery(document).on('mouseleave', '.tli-file-download .tli-download-text-link', function(event){
    jQuery(this).closest('.tli-file-download').find('i.fa-download').removeClass('fa-bounce');
});
