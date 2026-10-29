<?php

/*
 * The worlds of tests/Contract/CatalogAndWireContractTest.php. Block ids are the
 * SDK's own, computed here as a render computes them, so a seeded block is the
 * one a render looks for.
 */

use Langsys\SDK\Html\HtmlParser;

$parser = new HtmlParser();
$project = ['target_locales' => ['es-es']];
$hello = $project + ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]];

return [
    'index' => 3,
    'runs' => 14,
    'worlds' => [
        'writer' => ['count' => 4, 'projects' => ['p1' => $project], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'on-list' => ['projects' => ['p1' => $project], 'keys' => ['ip' => ['project' => 'p1', 'type' => 'ip_write', 'ip_allowlist' => ['@self']]]],
        'on-list-hello' => ['projects' => ['p1' => $hello], 'keys' => ['ip' => ['project' => 'p1', 'type' => 'ip_write', 'ip_allowlist' => ['@self']]]],
        'registered' => ['projects' => ['p1' => $project + ['phrases' => [['phrase' => 'Registered']]]], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        // Non-ASCII on purpose: for ASCII content a historical id shape equals
        // the current id, and the fallback would find the block a second way.
        'untranslated-block' => ['projects' => ['p1' => $project + ['blocks' => [[
            'category' => 'UI',
            'custom_id' => $parser->generateCustomId('UI', ['Café', 'Thé']),
            'content' => '<div><p>Café</p><p>Thé</p></div>',
            'phrases' => [['phrase' => 'Café'], ['phrase' => 'Thé']],
        ]]]], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'block-id-text' => ['projects' => ['p1' => $project + [
            'phrases' => [['phrase' => 'placeholder']],
            'blocks' => [[
                'category' => 'UI',
                'custom_id' => $parser->generateCustomId('UI', ['One', 'Two']),
                'content' => '<div><p>One</p><p>Two</p></div>',
                'phrases' => [['phrase' => 'One'], ['phrase' => 'Two']],
            ]],
        ]], 'keys' => ['write' => ['project' => 'p1', 'type' => 'write']]],
        'hello' => ['count' => 2, 'projects' => ['p1' => $hello], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']], 'unknown' => ['keys' => ['stranger']]],
        'uncategorised-block' => ['projects' => ['p1' => $project + ['blocks' => [[
            'category' => null,
            'custom_id' => $parser->generateCustomId(null, ['Café', 'Thé']),
            'content' => '<div><p>Café</p><p>Thé</p></div>',
            'phrases' => [['phrase' => 'Café', 'translations' => ['es-es' => 'Cafe ES']], ['phrase' => 'Thé', 'translations' => ['es-es' => 'Te ES']]],
        ]]]], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
    ],
];
