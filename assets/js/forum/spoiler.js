$(".spoiler_button").css("display", "block");


const SpoilerButton = {
    // Background position states
    states: {
        CLOSED: '0% 0%',
        CLOSED_HOVER: '0% -25px',
        OPENED: '0% -50px',
        OPENED_HOVER: '0% -75px'
    },

    tooltips: {
        show: "Clicca per mostrare il contenuto nascosto",
        hide: "Clicca per nascondere il contenuto"
    },

    getCurrentState(element) {
        return element.css("background-position");
    },

    setState(element, state) {
        element.css("background-position", state);
    },

    onHoverEnter(element) {
        const currentState = this.getCurrentState(element);

        if (currentState === this.states.CLOSED) {
            this.setState(element, this.states.CLOSED_HOVER);
        } else if (currentState === this.states.OPENED) {
            this.setState(element, this.states.OPENED_HOVER);
        }
    },

    onHoverLeave(element) {
        const currentState = this.getCurrentState(element);

        if (currentState === this.states.CLOSED_HOVER) {
            this.setState(element, this.states.CLOSED);
        } else if (currentState === this.states.OPENED_HOVER) {
            this.setState(element, this.states.OPENED);
        }
    },

    onClick(element) {
        element.toggleClass("spoiler_opened");

        if (element.hasClass("spoiler_opened")) {
            this.setState(element, this.states.OPENED);
            element.attr("title", this.tooltips.hide);
        } else {
            this.setState(element, this.states.CLOSED);
            element.attr("title", this.tooltips.show);
        }

        element.parent().children("div.spoiler_content").slideToggle();
    }
};


$(document).on("mouseenter", ".spoiler_button", function() {
    SpoilerButton.onHoverEnter($(this));
});

$(document).on("mouseleave", ".spoiler_button", function() {
    SpoilerButton.onHoverLeave($(this));
});

$(document).on("click", ".spoiler_button", function() {
    SpoilerButton.onClick($(this));
});
