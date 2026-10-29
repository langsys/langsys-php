<?php

/*
 * The worlds of tests/Contract/ServerMessagesContractTest.php, under the
 * messages category, `Errors`.
 */

$template = 'The password must be at least {min} characters.';
$project = ['target_locales' => ['es-es']];

return [
    'index' => 8,
    'runs' => 6,
    'worlds' => [
        'translated' => ['projects' => ['p1' => $project + ['phrases' => [['category' => 'Errors', 'phrase' => $template, 'translations' => ['es-es' => 'La contraseña debe tener al menos {min} caracteres.']]]]], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
        'one-listed' => ['projects' => ['p1' => $project + ['phrases' => [['category' => 'Errors', 'phrase' => 'The name is required.']]]], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'writer' => ['projects' => ['p1' => $project], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'reader' => ['projects' => ['p1' => $project], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
    ],
];
