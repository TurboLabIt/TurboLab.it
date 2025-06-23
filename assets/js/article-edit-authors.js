//import $ from 'jquery';


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
