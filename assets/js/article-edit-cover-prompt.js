const FLIP_BACK_DELAY_MS = 5000;
const HALF_FLIP_MS = 180;

const TITLE_SELECTOR = '[data-tli-editable-id="title"]';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');


async function copyText(text)
{
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        return false;
    }
}


// two half-turns around the vertical axis, swapping faces at 90°, so the box takes each face's own height.
// The inner card turns, not the container: edge-on, the container would lose the pointer and its hover
async function flip(container, showBack)
{
    const card  = container.querySelector('.tli-cover-prompt-card');
    const front = container.querySelector('.tli-cover-prompt-front');
    const back  = container.querySelector('.tli-cover-prompt-back');

    const swap = () => {
        front.hidden = showBack;
        back.hidden  = !showBack;
    };

    if( reducedMotion.matches || typeof card.animate !== 'function' ) {
        swap();
        return;
    }

    const options = {duration: HALF_FLIP_MS, easing: 'ease-in'};
    await card.animate([{transform: 'perspective(600px) rotateY(0deg)'}, {transform: 'perspective(600px) rotateY(90deg)'}], options).finished;
    swap();
    await card.animate([{transform: 'perspective(600px) rotateY(-90deg)'}, {transform: 'perspective(600px) rotateY(0deg)'}], {...options, easing: 'ease-out'}).finished;
}


document.querySelectorAll('.tli-cover-prompt').forEach(container => {

    const front     = container.querySelector('.tli-cover-prompt-front');
    const back      = container.querySelector('.tli-cover-prompt-back');
    const message   = container.querySelector('.tli-cover-prompt-message');
    const okMessage = message.innerHTML;

    // the article title, as typed so far, goes into the prompt: kept in data-prompt on every keystroke
    const titleField = document.querySelector(TITLE_SELECTOR);

    const buildPrompt = () => {

        const title = (titleField?.value ?? '').replace(/\s+/g, ' ').trim();
        container.dataset.prompt =
            title === ''
                ? container.dataset.promptUntitled
                : container.dataset.promptTitled.replace('{titolo}', () => title);

        return container.dataset.prompt;
    };

    buildPrompt();
    titleField?.addEventListener('input', buildPrompt);

    let flipped = false;
    let busy    = false;
    let timer   = null;

    const cancelFlipBack = () => {
        clearTimeout(timer);
        timer = null;
    };

    const scheduleFlipBack = () => {

        cancelFlipBack();
        if( !flipped ) {
            return;
        }

        timer = setTimeout(async () => {

            timer = null;
            if( container.matches(':hover') ) {
                return;
            }

            const hadFocus = container.contains(document.activeElement);
            flipped = false;
            busy    = true;
            await flip(container, false);
            busy    = false;
            if(hadFocus) {
                front.focus();
            }

        }, FLIP_BACK_DELAY_MS);
    };


    front.addEventListener('click', async event => {

        if( busy || flipped ) {
            return;
        }

        busy = true;
        const prompt = buildPrompt();
        const copied = await copyText(prompt);

        if(copied) {

            message.classList.remove('tli-cover-prompt-failed');
            message.innerHTML = okMessage;

        } else {

            message.classList.add('tli-cover-prompt-failed');
            message.textContent = "Copia non riuscita: seleziona e copia tu il prompt qui sotto, poi incollalo nella tua AI preferita.";
            const promptText = document.createElement('span');
            promptText.className = 'd-block mt-2 user-select-all';
            promptText.textContent = prompt;
            message.append(promptText);
        }

        flipped = true;
        await flip(container, true);
        busy = false;

        // activated from the keyboard: the button is gone, keep the focus inside
        if( event.detail === 0 ) {
            back.focus();
        }

        // a keyboard or a tap may leave no pointer over it
        if( !container.matches(':hover') ) {
            scheduleFlipBack();
        }
    });

    container.addEventListener('mouseenter', cancelFlipBack);
    container.addEventListener('mouseleave', scheduleFlipBack);
    container.addEventListener('focusin', cancelFlipBack);
    container.addEventListener('focusout', event => {
        if( !container.contains(event.relatedTarget) && !container.matches(':hover') ) {
            scheduleFlipBack();
        }
    });
});
