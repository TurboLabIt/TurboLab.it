$(".spoiler_button").css("display", "block");

$(".spoiler_button").hover(
    function(){
        if($(this).css("background-position") == "0% 0%"){
            $(this).css("background-position", "0% -25px");
        } else if($(this).css("background-position") == "0% -50px"){
            $(this).css("background-position", "0% -75px");
        }
    },
    function() {
        if($(this).css("background-position") == "0% -25px"){
            $(this).css("background-position", "0% 0%");
        } else if($(this).css("background-position") == "0% -75px"){
            $(this).css("background-position", "0% -50px");
        }
    }
);

$(".spoiler_button").click(function(){
    var currentBtn = $(this);
    currentBtn.toggleClass("spoiler_opened");
    if(currentBtn.hasClass("spoiler_opened")){
        currentBtn.css("background-position", "0% -50px");
        currentBtn.attr("title", "Clicca per nascondere il contenuto");
    } else {
        currentBtn.css("background-position", "0% 0%");
        currentBtn.attr("title", "Clicca per mostrare il contenuto nascosto");
    }
    $(this).parent().children("div.spoiler_content").slideToggle();
});
