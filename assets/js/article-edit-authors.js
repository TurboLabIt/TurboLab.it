//import $ from 'jquery';
import debounce from "./debouncer";


jQuery(document).on('click', '.tli-remove-author',  function(event) {

    event.preventDefault();

    let currentAuthorsList = $('#tli-ajax-modal .tli-article-editor-current-authors-list');
    let authorInList= $(this).closest('.list-group-item');
    //let authorId    = authorInList.data['author-id'];
    authorInList.fadeOut('slow', function(){

        authorInList.remove();
        let authorsNum = currentAuthorsList.find('[data-author-id]').length;
        currentAuthorsList.find('.tli-no-author-message').toggleClass('collapse', authorsNum != 0);
    });
});


jQuery(document).on('input', 'input.tli-authors-autocomplete', debounce(function() {

    let username = jQuery(this).val().trim();

    if( username == '' ) {
        return true;
    }

    let target = jQuery(this).closest('.tli-authors-autocomplete-container').find('.tli-article-editor-candidate-authors-list');

    //cleanup
    target.find('.tli-no-author-message').remove();
    target.find('[data-author-id]').remove();

    // show loaderino
    let loaderino = jQuery('#tli-ajax-modal').find('.tli-loaderino').first().clone().removeClass('collapse').prop('outerHTML');
    target.append(loaderino);

    target.show();

    // fetch results
    let endpoint = jQuery(this).data('autocomplete-url');

    jQuery.get(endpoint, {username: username}, function(data) {
        target.html(data);
    }, 'html')

        .fail(function(jqXHR, textStatus, errorThrown) {
            target.html(jqXHR.responseText);
        });

}, 300));
