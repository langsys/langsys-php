<?php

/*
 * The worlds of tests/Contract/CatalogFailureWindowContractTest.php: a phrase
 * translated into es-es, which a failed read cannot serve.
 */

return [
    'index' => 4,
    'runs' => 6,
    'worlds' => [
        'hello' => ['count' => 3, 'projects' => ['p1' => ['target_locales' => ['es-es'], 'phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]]], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
    ],
];
