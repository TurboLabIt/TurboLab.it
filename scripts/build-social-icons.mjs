// Generates the social icons (brand-color disc + white glyph) consumed by
// templates/parts/social-share.html.twig, FrontendHelper and the home video player.
// Sources: simple-icons (official glyphs + brand hex) and Font Awesome, which provides
// positive-space glyphs where the simple-icons full-logo shape would render inverted
// on a colored disc (and the generic envelope, which is not a brand).
// Output: assets/images/social-icons/ (gitignored) -> hashed by Encore copyFiles.
// Runs via `yarn build:icons` (hooked into the dev/watch/build scripts).

import * as si from 'simple-icons';
import { createRequire } from 'node:module';
import { mkdirSync, rmSync, writeFileSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const require = createRequire(import.meta.url);
const OUT_DIR = path.join(path.dirname(fileURLToPath(import.meta.url)), '../assets/images/social-icons');

const SI_LICENSE = '<!-- Glyph: Simple Icons - https://simpleicons.org (CC0; brands are trademarks of their owners) -->';
const FA_LICENSE = '<!-- Glyph: Font Awesome Free - https://fontawesome.com/license/free (Icons: CC BY 4.0) -->';
const BI_LICENSE = '<!-- Glyph: Bootstrap Icons - https://icons.getbootstrap.com (MIT) -->';

// source: si = simple-icons export name | fa = Font Awesome svgs/<style>/<name>.svg
// color: disc background; defaults to the simple-icons official hex when omitted
const ICONS = {
    'facebook.svg':  { fa: 'brands/facebook-f',    color: si.siFacebook.hex },
    'x-twitter.svg': { si: 'siX' },
    'whatsapp.svg':  { si: 'siWhatsapp' },
    'telegram.svg':  { si: 'siTelegram' },
    'linkedin.svg':  { fa: 'brands/linkedin-in',   color: '0A66C2' },
    'reddit.svg':    { fa: 'brands/reddit-alien',  color: si.siReddit.hex },
    'teams.svg':     { bi: 'microsoft-teams',      color: '6264A7' },
    'slack.svg':     { fa: 'brands/slack',         color: '4A154B' },
    'email.svg':     { fa: 'solid/envelope',       color: 'C10000' },
    'youtube.svg':   { si: 'siYoutube' },
    'rss.svg':       { fa: 'solid/rss',            color: si.siRss.hex },
    'github.svg':    { si: 'siGithub' },
};

const GLYPH_RATIO = 0.6;

function glyphFromSimpleIcons(exportName) {
    const icon = si[exportName];
    if( !icon ) throw new Error(`simple-icons export not found: ${exportName}`);
    return { d: icon.path, width: 24, height: 24, hex: icon.hex, license: SI_LICENSE };
}

function glyphFromBootstrapIcons(name) {
    const file = require.resolve(`bootstrap-icons/icons/${name}.svg`);
    const svg = readFileSync(file, 'utf8');
    const vb = svg.match(/viewBox="0 0 ([\d.]+) ([\d.]+)"/);
    const ds = [...svg.matchAll(/<path[^>]*\bd="([^"]+)"/g)].map(m => m[1]);
    if( !vb || !ds.length ) throw new Error(`cannot parse Bootstrap Icons svg: ${name}`);
    return { d: ds.join(' '), width: parseFloat(vb[1]), height: parseFloat(vb[2]), hex: null, license: BI_LICENSE };
}

function glyphFromFontAwesome(name) {
    const file = require.resolve(`@fortawesome/fontawesome-free/svgs/${name}.svg`);
    const svg = readFileSync(file, 'utf8');
    const vb = svg.match(/viewBox="0 0 ([\d.]+) ([\d.]+)"/);
    const d = svg.match(/<path[^>]*\bd="([^"]+)"/)?.[1];
    if( !vb || !d ) throw new Error(`cannot parse Font Awesome svg: ${name}`);
    return { d, width: parseFloat(vb[1]), height: parseFloat(vb[2]), hex: null, license: FA_LICENSE };
}

rmSync(OUT_DIR, { recursive: true, force: true });
mkdirSync(OUT_DIR, { recursive: true });

for( const [file, recipe] of Object.entries(ICONS) ) {

    const glyph = recipe.si ? glyphFromSimpleIcons(recipe.si)
                : recipe.bi ? glyphFromBootstrapIcons(recipe.bi)
                : glyphFromFontAwesome(recipe.fa);
    const color = '#' + (recipe.color ?? glyph.hex).toLowerCase();

    const s  = +(GLYPH_RATIO * 512 / Math.max(glyph.width, glyph.height)).toFixed(4);
    const tx = +((512 - glyph.width  * s) / 2).toFixed(2);
    const ty = +((512 - glyph.height * s) / 2).toFixed(2);

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">${glyph.license}` +
        `<circle cx="256" cy="256" r="256" fill="${color}"/>` +
        `<path fill="#fff" transform="translate(${tx} ${ty}) scale(${s})" d="${glyph.d}"/></svg>\n`;

    writeFileSync(path.join(OUT_DIR, file), svg);
    console.log(`${file.padEnd(14)} ${color}  glyph: ${recipe.si ?? recipe.bi ?? recipe.fa}`);
}

console.log(`${Object.keys(ICONS).length} icons written to ${OUT_DIR}`);
