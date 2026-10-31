<?php

/*
 * The worlds of tests/Contract/ImportContractTest.php: a project translating
 * into es-es and fr-fr, the import's targets.
 */

$project = ['target_locales' => ['es-es', 'fr-fr']];

return [
    'index' => 6,
    'runs' => 6,
    'worlds' => [
        'importing' => ['count' => 2, 'projects' => ['p1' => $project], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'capped' => ['projects' => ['p1' => $project + ['human_translation_word_limit' => 2]], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'reader' => ['projects' => ['p1' => ['target_locales' => ['es-es']]], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
    ],
];
