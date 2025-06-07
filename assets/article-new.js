//import $ from 'jquery';
import './js/slider';
import './styles/slider-custom-arrows.css';


function showNewArticleForm()
{
    let searchForm = jQuery('#search-article-form');
    searchForm.find('.input-group-lg')
        .removeClass('input-group-lg')
        .addClass('input-group-sm');

    searchForm.find('.fa-shake')
        .removeClass('fa-shake');

    let inputNewTitle = jQuery('#new-article-title');
    inputNewTitle
        .prop('disabled', false)
        .attr('placeholder', inputNewTitle.data('placeholder-after-search'))
        .focus();

    let newTitleForm = inputNewTitle.closest('form');
    newTitleForm.find('.input-group-sm')
        .removeClass('input-group-sm')
        .addClass('input-group-lg')

    let btnSubmit = newTitleForm.find('button[type=submit]')
    btnSubmit
        .prop('disabled', false);

    btnSubmit.find('i')
        .addClass('fa-shake');
}


jQuery(document).on('submit', '#search-article-form', function(event) {
    showNewArticleForm();
});


jQuery(document).on('dblclick', '.show-new-article-form', function(event) {
    showNewArticleForm();
});
