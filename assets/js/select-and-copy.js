export default function selectAndCopy(target)
{
    if( typeof target == 'string' && target.trim() == '' ) {
        throw new TypeError('target must be a non-empty string');
    }

    let elements;

    if( typeof target == 'string' ) {

        elements = Array.from(document.querySelectorAll(target));
        if (elements.length === 0) {
            throw new Error(`No elements match selector: ${target}`);
        }

    } else {

        elements = [target];
    }

    // Build plain text (only)
    const textFor = (el) =>
        el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement
            ? el.value
            : (el.innerText ?? el.textContent ?? '');

    const plain = elements.map(textFor).join('\n');

    // Visually select before copying
    const sel = window.getSelection();
    sel.removeAllRanges();

    if (
        elements.length === 1 &&
        (elements[0] instanceof HTMLInputElement || elements[0] instanceof HTMLTextAreaElement)
    ) {
        elements[0].focus();
        elements[0].select(); // highlights the text inside the field
    } else {
        const first = elements[0];
        const last = elements[elements.length - 1];

        const range = document.createRange();
        const startNode = first.firstChild ?? first;
        const endNode = last.lastChild ?? last;

        range.setStartBefore(startNode);
        range.setEndAfter(endNode);
        sel.addRange(range);
    }

    //copy
    navigator.clipboard.writeText(plain);
}
