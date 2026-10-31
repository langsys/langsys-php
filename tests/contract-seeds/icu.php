<?php

/*
 * The worlds of tests/Contract/IcuContractTest.php: a plural promoted to ICU
 * on the base locale, which the catalog serves as its message only to a client
 * that asks for it.
 */

return [
    'index' => 13,
    'runs' => 6,
    'worlds' => [
        'plural' => ['count' => 2, 'projects' => ['p1' => [
            'base_locale' => 'en',
            'target_locales' => ['es-es'],
            'phrases' => [['category' => 'UI', 'phrase' => '{count} files', 'translations' => ['en' => '{count, plural, one {# file} other {# files}}']]],
        ]], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
    ],
];
