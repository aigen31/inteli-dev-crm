<?php

declare(strict_types=1);

return [
    /*
     * The app keeps pages in `resources/js/Pages` (capital P). The package
     * default points at `js/pages`, which is case-sensitive on Linux and made
     * `assertInertia` unable to resolve any component.
     */
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],
];
