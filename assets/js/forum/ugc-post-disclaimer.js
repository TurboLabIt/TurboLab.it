var irrilevante=true;

if( (location.pathname=="/forum/posting.php" 	&& $("#tabs").length) ||
    (location.pathname=="/forum/viewtopic.php" 	&& $("#qr_postform>div>div").length) )
{
    //Stabilisco se l'utente è loggato
    var checkThese=$('#nav-main>li>a');
    var userLogged=true;

    checkThese.each(function( index ) {
        if(this.text == "Login")
        { userLogged=false; }
    });

    if(userLogged && location.pathname=="/forum/posting.php")
    {
        $('#condizioni-inserimento-messaggi-forum').insertBefore('#tabs');
        irrilevante=false;
    }

    else if(userLogged && location.pathname=="/forum/viewtopic.php")
    {
        $('#condizioni-inserimento-messaggi-forum').appendTo('#qr_postform>div>div');
        irrilevante=false;
    }
}

if(irrilevante)
{ $('#condizioni-inserimento-messaggi-forum').css("display", "none"); }
