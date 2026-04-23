<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'bootstrap' => [
        'version' => '3.4.1',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap-sass' => [
        'version' => '3.4.3',
    ],
    'autocompleter' => [
        'version' => '9.3.2',
    ],
    'autocompleter/autocomplete.min.css' => [
        'version' => '9.3.2',
        'type' => 'css',
    ],
    'jssor-slider' => [
        'version' => '28.0.0',
    ],
    'blueimp-file-upload' => [
        'version' => '10.32.0',
    ],
    'leaflet' => [
        'version' => '1.9.4',
    ],
    'leaflet/dist/leaflet.min.css' => [
        'version' => '1.9.4',
        'type' => 'css',
    ],
    'summernote' => [
        'version' => '0.9.1',
    ],
    'codemirror' => [
        'version' => '5.65.12',
    ],
    'codemirror/lib/codemirror.min.css' => [
        'version' => '5.65.12',
        'type' => 'css',
    ],
    'bootstrap-tagsinput' => [
        'version' => '0.7.1',
    ],
    'jquery' => [
        'version' => '3.7.1',
    ],
    'bootstrap-datepicker' => [
        'version' => '1.10.1',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    'datatables.net' => [
        'version' => '2.3.6',
    ],
    'datatables.net-bs' => [
        'version' => '2.3.6',
    ],
    'datatables.net-bs/css/dataTables.bootstrap.min.css' => [
        'version' => '2.3.6',
        'type' => 'css',
    ],
    'datatables.net-responsive' => [
        'version' => '3.0.8',
    ],
    'datatables.net-responsive-bs' => [
        'version' => '3.0.8',
    ],
    'datatables.net-responsive-bs/css/responsive.bootstrap.min.css' => [
        'version' => '3.0.8',
        'type' => 'css',
    ],
];
