export default class Validator
{
    /**
     * True if `s` is a non-empty, same-origin https URL.
     *
     * Used to lock down URLs we trust to varying degrees: AJAX targets whose
     * response we'll inject as HTML, and redirect destinations we'll assign
     * to window.location. Rejects javascript:/data:/vbscript: schemes, plain
     * http, cross-origin URLs, protocol-relative //host, subdomain and
     * userinfo tricks, and empty/null/undefined.
     */
    static isSameOriginHttpsUrl(s)
    {
        if( !s )  return false;
        try {
            const url = new URL(String(s), window.location.origin);
            if( url.protocol !== 'https:' )  return false;
            return url.origin === window.location.origin;
        } catch(e) {
            return false;
        }
    }


    /**
     * Returns `s` if it is safe to use as an <a href> value (an internal
     * absolute path, or an explicit https:// URL); otherwise '#'.
     *
     * HTML-escaping alone isn't enough for an href: browsers decode entities
     * in URL attributes before navigating, so `javascript:alert(1)` survives
     * escapeHtml() and still fires on click. Callers should still HTML-escape
     * the returned value before interpolating it into markup.
     */
    static sanitizeHref(s)
    {
        s = String(s);
        if( s.startsWith('/') && !s.startsWith('//') )  return s;
        if( /^https:\/\//i.test(s) )                    return s;
        return '#';
    }
}
