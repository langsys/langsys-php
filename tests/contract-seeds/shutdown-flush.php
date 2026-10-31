<?php

/*
 * The worlds of tests/Contract/ShutdownFlushContractTest.php.
 */

$project = ['target_locales' => ['es-es']];

return [
    'index' => 9,
    'runs' => 6,
    'worlds' => [
        'writer' => ['count' => 2, 'projects' => ['p1' => $project], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'reader' => ['projects' => ['p1' => $project], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
    ],
];
