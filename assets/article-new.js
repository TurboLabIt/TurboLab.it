//import $ from 'jquery';

jQuery(document).on('submit', '.show-new-article', function(event) {

    let searchForm = jQuery(this);
    searchForm.find('.input-group-lg')
        .removeClass('input-group-lg')
        .addClass('input-group-sm');

    searchForm.find('.fa-shake')
        .removeClass('fa-shake');

    let inputNewTitle = jQuery('#new-article-title');
    inputNewTitle
        .prop('disabled', false)
        .attr('placeholder', inputNewTitle.data('placeholder-after-search'));

    let newTitleForm = inputNewTitle.closest('form');
    newTitleForm.find('.input-group-sm')
        .removeClass('input-group-sm')
        .addClass('input-group-lg')

    let btnSubmit = newTitleForm.find('button[type=submit]')
    btnSubmit
        .prop('disabled', false);

    btnSubmit.find('i')
        .addClass('fa-shake');
});
