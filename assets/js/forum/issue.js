//import $ from 'jquery';

$(document).on('click', '.tli-create-issue',  function(event) {

    event.preventDefault();
    let bugButton = $(this);

    if( bugButton.hasClass('tli-action-running') ) {

        alert("Creazione issue in corso. Potrai crearne un'altra fra poco");
        return false;
    }

    bugButton.addClass("tli-action-running");

    let bugIcon = bugButton.find('i');
    bugIcon.addClass("fa-spin");

    $.post(bugButton.attr('href'), bugButton.data())

        .done( function(response) {

            alert("OK, grazie per il tuo contributo! La pagina verrà ora ricaricata per mostrarti il link alla issue che hai appena creato");
            window.location = response;
        })

        .fail( function(jqXHR, responseText) {
            alert(jqXHR.responseText);
        })

        .always(function(){

            bugIcon.removeClass("fa-spin");
            bugButton.removeClass('tli-action-running')
        });
});
