var Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry('app', './assets/js/app.js')
    .addEntry('footable', './assets/js/footable.js')
    .addEntry('datepicker', './assets/js/datepicker.js')
    .addEntry('typeahead', './assets/js/typeahead.js')
    .addEntry('summernote', './assets/js/summernote.js')
    .addEntry('sortable', './assets/js/sortable.js')
    .addEntry('fileupload', './assets/js/fileupload.js')
    .addEntry('transactions_add', './assets/js/transactions_add.js')

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })

    // enables Sass/SCSS support
    .enableSassLoader()

    // uncomment if you use TypeScript
    //.enableTypeScriptLoader()

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    //.enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()
;

var config = Encore.getWebpackConfig();

config.resolve.alias = {
    'load-image': 'blueimp-load-image/js/load-image.js',
    'load-image-meta': 'blueimp-load-image/js/load-image-meta.js',
    'load-image-exif': 'blueimp-load-image/js/load-image-exif.js',
    'load-image-orientation': 'blueimp-load-image/js/load-image-orientation.js',
    'load-image-scale': 'blueimp-load-image/js/load-image-scale.js',
    'load-image-fetch': 'blueimp-load-image/js/load-image-fetch',
    'load-image-iptc': 'blueimp-load-image/js/load-image-iptc',
    'canvas-to-blob': 'blueimp-canvas-to-blob/js/canvas-to-blob.js',
    'jquery-ui/widget': 'blueimp-file-upload/js/vendor/jquery.ui.widget.js'
 }

module.exports = config;
