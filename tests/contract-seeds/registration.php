<?php

/*
 * The worlds of tests/Contract/RegistrationContractTest.php. A project accepts
 * a hint only while it machine-translates new content, so the worlds a hint is
 * stored in turn it on; no other world does, and nothing PHP registers in them
 * reaches machine translation.
 */

$project = ['target_locales' => ['es-es']];
$reporting = ['target_locales' => ['es-es'], 'website_url' => 'https://site.local', 'machine_translate_new_phrases' => true];

return [
    'index' => 2,
    'runs' => 10,
    'worlds' => [
        'writer' => ['count' => 10, 'projects' => ['p1' => $project], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'known' => ['count' => 2, 'projects' => ['p1' => $project + ['phrases' => [['phrase' => 'Known', 'translations' => ['es-es' => 'Conocido']]]]], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'reader' => ['projects' => ['p1' => $project], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
        // May write from the case's own address, and permitted to report once it moves off it.
        'reporting-writer' => ['projects' => ['p1' => $reporting], 'keys' => ['ip' => ['project' => 'p1', 'type' => 'ip_write', 'ip_allowlist' => ['@self'], 'report_discovered_content' => true]]],
        // Read-only from the case's own address, and permitted to report.
        'reporting-reader' => ['projects' => ['p1' => $reporting], 'keys' => ['ip' => ['project' => 'p1', 'type' => 'ip_write', 'ip_allowlist' => ['@moved'], 'report_discovered_content' => true]]],
        // Read-only from the case's own address, writing once it moves.
        'held-back' => ['projects' => ['p1' => $project], 'keys' => ['ip' => ['project' => 'p1', 'type' => 'ip_write', 'ip_allowlist' => ['@moved']]]],
    ],
];
