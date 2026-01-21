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
        'version' => '5.3.8',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.8',
        'type' => 'css',
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
    'bootstrap-tagsinput' => [
        'version' => '0.7.1',
    ],
    'jssor-slider' => [
        'version' => '28.0.0',
    ],
    'jquery' => [
        'version' => '3.7.1',
    ],
    'blueimp-file-upload' => [
        'version' => '10.32.0',
    ],
    'bootstrap-datepicker' => [
        'version' => '1.10.1',
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
];
