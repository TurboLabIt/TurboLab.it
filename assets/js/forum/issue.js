//import $ from 'jquery';
const TLI_BUG_GUIDE_READ_COOKIE_NAME = 'tli-bug-guide-read';
const TLI_BUG_GUIDE_ID = '49';


$(document).on('click', '.tli-create-issue',  function(event) {

    event.preventDefault();

    let bugButton = $(this);

    if( bugButton.hasClass('tli-action-running') ) {

        alert("Creazione issue in corso. Potrai crearne un'altra fra poco");
        return false;
    }

    if( Cookies.get(TLI_BUG_GUIDE_READ_COOKIE_NAME) != 1 ) {

        Cookies.set(TLI_BUG_GUIDE_READ_COOKIE_NAME, '1', {expires: 30, path: '/', sameSite: 'lax', secure: true});
        window.open('/' + TLI_BUG_GUIDE_ID, '_blank');
        return false;
    }

    if( !confirm(
            "Stai per creare una nuova issue su GitHub.\n\nPer favore, (ri)leggi sempre " +
            "📚 https://turbolab.it/" + TLI_BUG_GUIDE_ID + " prima di procedere"
        )
    ) {
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
