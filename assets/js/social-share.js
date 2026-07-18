// Invariant: while the pointer is inside the toolbar, no pill may change width except the
// hovered one (growing) and flex-shrunk expanded pills (monotonically), or the row slides
// under the cursor and the user clicks the wrong pill. Expanded pills therefore never
// collapse: every pill the user settles on stays expanded, and the row flex-squeezes them
// to fit. The timer ignores pills merely crossed on the way to the target. On touch and
// narrow viewports (no cursor, no aiming problem) the previous pill collapses instead.

let tliShareIntentTimer = null;

jQuery(document).on('mouseenter', '.tli-social-share a', function(){

    const Pill = jQuery(this);
    clearTimeout(tliShareIntentTimer);

    tliShareIntentTimer = setTimeout(function(){

        const Toolbar = Pill.closest('.tli-social-share');

        if( !window.matchMedia('(hover: hover) and (min-width: 768px)').matches ) {
            Toolbar.find('a.tli-share-featured').not(Pill).removeClass('tli-share-featured');
        }

        Toolbar.find('a.tli-share-active').not(Pill).removeClass('tli-share-active');
        Pill.addClass('tli-share-featured tli-share-active');

    }, 150);
});

jQuery(document).on('mouseleave', '.tli-social-share a', function(){
    clearTimeout(tliShareIntentTimer);
});

jQuery(document).on('focusin', '.tli-social-share a', function(){
    jQuery(this).closest('.tli-social-share').find('a.tli-share-featured').not(this).removeClass('tli-share-featured tli-share-active');
    jQuery(this).addClass('tli-share-featured tli-share-active');
});
