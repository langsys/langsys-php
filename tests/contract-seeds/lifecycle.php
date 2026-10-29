<?php

/*
 * The worlds of tests/Contract/LifecycleContractTest.php: a write key whose
 * sends a case fails, and the keys whose write decision OBS-1 reports on.
 */

$project = ['target_locales' => ['es-es']];

return [
    'index' => 14,
    'runs' => 8,
    'worlds' => [
        'writer' => ['count' => 3, 'projects' => ['p1' => $project], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        // Read-only from the case's own address, writing from the moved one.
        'off-list' => ['projects' => ['p1' => $project], 'keys' => ['ip' => ['project' => 'p1', 'type' => 'ip_write', 'ip_allowlist' => ['@moved']]]],
        'reader' => ['projects' => ['p1' => $project], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
    ],
];
