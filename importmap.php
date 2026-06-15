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
        'version' => '3.2.1',
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
    '@rails/request.js' => [
        'version' => '0.0.8',
    ],
    'sortablejs' => [
        'version' => '1.15.7',
    ],
    '@tiptap/core' => [
        'version' => '3.23.1',
    ],
    '@tiptap/starter-kit' => [
        'version' => '3.23.1',
    ],
    '@tiptap/pm/transform' => [
        'version' => '3.23.1',
    ],
    '@tiptap/pm/commands' => [
        'version' => '3.23.1',
    ],
    '@tiptap/pm/state' => [
        'version' => '3.23.1',
    ],
    '@tiptap/pm/model' => [
        'version' => '3.23.1',
    ],
    '@tiptap/pm/schema-list' => [
        'version' => '3.23.1',
    ],
    '@tiptap/pm/view' => [
        'version' => '3.23.1',
    ],
    '@tiptap/pm/keymap' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-blockquote' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-bold' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-code' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-code-block' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-document' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-hard-break' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-heading' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-horizontal-rule' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-italic' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-link' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-list' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-paragraph' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-strike' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-text' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extension-underline' => [
        'version' => '3.23.1',
    ],
    '@tiptap/extensions' => [
        'version' => '3.23.1',
    ],
    'prosemirror-transform' => [
        'version' => '1.12.0',
    ],
    'prosemirror-commands' => [
        'version' => '1.7.1',
    ],
    'prosemirror-state' => [
        'version' => '1.4.4',
    ],
    'prosemirror-model' => [
        'version' => '1.25.4',
    ],
    'prosemirror-schema-list' => [
        'version' => '1.5.1',
    ],
    'prosemirror-view' => [
        'version' => '1.41.8',
    ],
    'prosemirror-keymap' => [
        'version' => '1.2.3',
    ],
    '@tiptap/core/jsx-runtime' => [
        'version' => '3.23.1',
    ],
    'linkifyjs' => [
        'version' => '4.3.2',
    ],
    '@tiptap/pm/dropcursor' => [
        'version' => '3.23.1',
    ],
    '@tiptap/pm/gapcursor' => [
        'version' => '3.23.1',
    ],
    '@tiptap/pm/history' => [
        'version' => '3.23.1',
    ],
    'orderedmap' => [
        'version' => '2.1.1',
    ],
    'w3c-keyname' => [
        'version' => '2.2.8',
    ],
    'prosemirror-dropcursor' => [
        'version' => '1.8.2',
    ],
    'prosemirror-gapcursor' => [
        'version' => '1.4.1',
    ],
    'prosemirror-history' => [
        'version' => '1.5.0',
    ],
    'prosemirror-view/style/prosemirror.min.css' => [
        'version' => '1.41.8',
        'type' => 'css',
    ],
    'rope-sequence' => [
        'version' => '1.3.4',
    ],
    'prosemirror-gapcursor/style/gapcursor.min.css' => [
        'version' => '1.4.1',
        'type' => 'css',
    ],
    '@tiptap/extension-image' => [
        'version' => '3.23.1',
    ],
    'bootstrap-sass/assets/javascripts/bootstrap.js' => [
        'version' => '3.4.3',
    ],
    '@yaireo/tagify' => [
        'version' => '4.37.1',
    ],
    '@yaireo/tagify/dist/tagify.css' => [
        'version' => '4.37.1',
        'type' => 'css',
    ],
];
