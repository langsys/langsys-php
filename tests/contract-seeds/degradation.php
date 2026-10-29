<?php

/*
 * The worlds of tests/Contract/DegradationContractTest.php: a phrase translated
 * into es-es, which a failed read cannot serve.
 */

$project = ['target_locales' => ['es-es']];

return [
    'index' => 5,
    'runs' => 6,
    'worlds' => [
        'hello' => ['count' => 4, 'projects' => ['p1' => $project + ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]]], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'writer' => ['projects' => ['p1' => $project], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
    ],
];
