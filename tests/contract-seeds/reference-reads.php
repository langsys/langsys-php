<?php

/*
 * The worlds of tests/Contract/ReferenceReadsContractTest.php: a project
 * holding every locale a case reads in, and two read keys, so a read under
 * other credentials has its own.
 */

$project = ['target_locales' => ['es-es', 'fr-fr']];
$keys = ['k1' => ['project' => 'p1', 'type' => 'read'], 'k2' => ['project' => 'p1', 'type' => 'read']];

return [
    'index' => 12,
    'runs' => 8,
    'worlds' => [
        'lists' => ['count' => 6, 'projects' => ['p1' => $project], 'keys' => $keys],
        'posix' => ['projects' => ['p1' => $project + ['locale_code_format' => 'posix']], 'keys' => $keys],
    ],
];
