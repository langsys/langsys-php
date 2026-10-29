<?php

/*
 * The worlds of tests/Contract/WriteDecisionContractTest.php: one project
 * translating into es-es, and the key whose write decision a case reads.
 */

$project = ['p1' => ['target_locales' => ['es-es']]];

return [
    'index' => 1,
    'runs' => 8,
    'worlds' => [
        'writer' => ['projects' => $project, 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'reader' => ['projects' => $project, 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
        'listed-exact' => ['projects' => $project, 'keys' => ['ip' => ['project' => 'p1', 'type' => 'ip_write', 'ip_allowlist' => ['@self']]]],
        'listed-range' => ['projects' => $project, 'keys' => ['ip' => ['project' => 'p1', 'type' => 'ip_write', 'ip_allowlist' => ['@net']]]],
        'off-list' => ['projects' => $project, 'keys' => ['ip' => ['project' => 'p1', 'type' => 'ip_write', 'ip_allowlist' => ['@moved']]]],
    ],
];
