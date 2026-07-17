import Encore from '@symfony/webpack-encore';

// Manually configure the runtime environment if the "encore" command hasn't already done it
// (useful for tools that read webpack.config.js directly).
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // where compiled assets are written, and the public path the web server serves them from
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    /*
     * ENTRY CONFIG — each entry produces one JS file (e.g. app.js) plus, if that JS imports CSS,
     * one CSS file (e.g. app.css). See docs/assets-frontend.md.
     */
    .addEntry('app', './assets/app.js')
    .addEntry('home', './assets/home.js')
    .addEntry('archive', './assets/archive.js')
    .addEntry('article', './assets/article.js')
    .addEntry('article-new', './assets/article-new.js')
    .addEntry('article-edit', './assets/article-edit.js')
    .addEntry('calendar', './assets/calendar.js')
    .addEntry('search', './assets/search.js')
    .addEntry('stats', './assets/stats.js')
    .addEntry('forum', './assets/forum.js')

    // single shared runtime.js chunk (recommended unless building a SPA)
    .enableSingleRuntimeChunk()

    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    // hashed filenames in production only (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // minimal @babel/preset-env: no polyfills, target ES-module-capable browsers
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = false;
        config.targets = { esmodules: true };
    })

    .copyFiles({
        from: './assets/images',
        to: 'images/[path][name].[hash:8].[ext]',
    })
;

// Since Webpack Encore 7.0 the config is ESM-only and getWebpackConfig() returns a Promise.
const config = await Encore.getWebpackConfig();

// The asset entrypoints use extensionless relative imports (e.g. `import '…/js/foo'`). With
// package.json "type":"module" webpack treats them as strict ESM and would demand fully-specified
// paths; this keeps normal extension resolution working without touching every import.
config.module.rules.push({ test: /\.m?js$/, resolve: { fullySpecified: false } });

// slick-carousel is a jQuery plugin that does `require('jquery')` internally, but jQuery is loaded as a
// CDN global (see base.html.twig), not a bundled package. Map that import to the global `jQuery` so slick
// binds to the same instance the rest of the site uses — and so Yarn PnP doesn't try to resolve a package
// that isn't installed.
config.externals = { ...(config.externals ?? {}), jquery: 'jQuery' };

export default config;
