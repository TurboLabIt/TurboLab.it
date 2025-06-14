/**
 * Usage
 * =====
 * import debounce from './js/debouncer';
 *
 */

export default function(func, delay)
{
    let timeout;
    return function (...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), delay);
    };
}
