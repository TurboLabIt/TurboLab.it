import { Plugin, ButtonView } from 'ckeditor5';

export default class TliYoutube extends Plugin {
    static get pluginName() { return 'TliYoutube'; }

    init() {
        const editor = this.editor;

        // Toolbar button
        editor.ui.componentFactory.add('tliyoutube', locale => {
            const view = new ButtonView(locale);
            view.set({
                icon: $('#tli-toolbar-icons .bi-youtube')[0].outerHTML,
                tooltip: 'Inserisci filmato YouTube',
                withText: false
            });

            view.on('execute', () => {
                const input = (window.prompt("Incolla qui l'URL del filmato da inserire") || '').trim();

                if( input == '' ) {
                    return false;
                }

                const id = extractYouTubeVideoId(input);

                if (!id) {
                    window.alert(
                        "URL invalido! Incolla l'URL di un video YouTube. Ad esempio:\n" +
                        "  https://www.youtube.com/watch?v=abcdef12345\n" +
                        "  https://youtu.be/abcdef12345\n" +
                        "  https://www.youtube.com/shorts/abcdef12345"
                    );
                    return;
                }

                const html =
                    `<iframe src="https://www.youtube-nocookie.com/embed/${id}?rel=0" frameborder="0" width="100%" height="540"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen></iframe>`;

                // Insert the raw HTML via the data pipeline.
                editor.model.change(() => {
                    const viewFragment = editor.data.processor.toView(html);
                    const modelFragment = editor.data.toModel(viewFragment);
                    editor.model.insertContent(modelFragment, editor.model.document.selection);
                });
            });

            return view;
        });
    }

    // Allow <iframe> (and needed attributes) if GeneralHtmlSupport is present.
    afterInit() {
        const editor = this.editor;

        if (!editor.plugins.has('GeneralHtmlSupport')) return;

        const dataFilter = editor.plugins.get('DataFilter');
        if (!dataFilter) return;

        dataFilter.allowElement('iframe');
        dataFilter.allowAttributes({
            name: 'iframe',
            attributes: {
                src: true,
                frameborder: true,
                width: true,
                height: true,
                allow: true,
                allowfullscreen: true
            }
        });
    }
}

/**
 * Extract a YouTube video ID (11 chars) from common single-video URL formats.
 * Accepts: watch?v=, youtu.be/, embed/, youtube-nocookie.com/embed/, shorts/.
 * Rejects: URLs without a valid video id (e.g., playlist-only links).
 */
function extractYouTubeVideoId(input)
{
    const ID_RE = /^[A-Za-z0-9_-]{11}$/;

    // Normalize / add scheme if missing (supports "youtube.com/...", "//youtube.com/...")
    let s = input.trim();
    if (!/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(s)) {
        s = s.startsWith('//') ? 'https:' + s : 'https://' + s.replace(/^\/\//, '');
    }

    try {
        const u = new URL(s);
        const host = u.hostname.toLowerCase();

        // Accept only YouTube domains
        const isYouTubeHost =
            host === 'youtu.be' ||
            host.endsWith('.youtube.com') ||
            host === 'youtube.com' ||
            host.endsWith('.youtube-nocookie.com') ||
            host === 'youtube-nocookie.com';

        if (!isYouTubeHost) return null;

        const params = u.searchParams;

        // 1) watch?v=VIDEOID (works on youtube.com, m.youtube.com, music.youtube.com, etc.)
        const v = params.get('v');
        if (v && ID_RE.test(v)) return v;

        // 2) youtu.be/VIDEOID
        if (host === 'youtu.be') {
            const m = u.pathname.match(/^\/([A-Za-z0-9_-]{11})(?:[/?].*)?$/);
            if (m) return m[1];
        }

        // 3) /embed/VIDEOID  (also on youtube-nocookie.com)
        let m = u.pathname.match(/\/embed\/([A-Za-z0-9_-]{11})(?:[/?].*)?$/);
        if (m) return m[1];

        // 4) /shorts/VIDEOID
        m = u.pathname.match(/\/shorts\/([A-Za-z0-9_-]{11})(?:[/?].*)?$/);
        if (m) return m[1];

        // 5) Fallback: scan the full string for known patterns
        m = s.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:.*[?&]v=|embed\/|shorts\/))([A-Za-z0-9_-]{11})/i);
        if (m) return m[1];

        return null;
    } catch {
        // If URL constructor failed, try a relaxed fallback directly on the input.
        const m = s.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:.*[?&]v=|embed\/|shorts\/))([A-Za-z0-9_-]{11})/i);
        return m ? m[1] : null;
    }
}
