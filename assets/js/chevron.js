//import $ from 'jquery';

$(document).on('show.bs.collapse', '.tli-contains-chevron', function() {
    $(this).find('.chevron').addClass('tli-chevron-rotated');
});


$(document).on('hide.bs.collapse', '.tli-contains-chevron', function() {
    $(this).find('.chevron').removeClass('tli-chevron-rotated');
});
