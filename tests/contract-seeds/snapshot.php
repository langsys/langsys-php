<?php

/*
 * The worlds of tests/Contract/SnapshotContractTest.php: a catalog across
 * three categories and two locales, with a block and untranslated entries.
 */

return [
    'index' => 10,
    'runs' => 4,
    'worlds' => [
        'catalog' => ['count' => 3, 'projects' => ['p1' => [
            'target_locales' => ['es-es', 'fr-fr'],
            'phrases' => [
                ['category' => 'UI', 'phrase' => 'Save', 'translations' => ['es-es' => 'Guardar', 'fr-fr' => 'Enregistrer']],
                ['category' => 'Errors', 'phrase' => 'Oops', 'translations' => ['es-es' => 'Uy']],
                ['category' => 'Billing', 'phrase' => 'Pay', 'translations' => ['es-es' => 'Pagar']],
                ['category' => null, 'phrase' => 'Loose'],
            ],
            'blocks' => [['category' => 'UI', 'custom_id' => 'abc123', 'content' => '<div><p>One</p><p>Two</p></div>', 'phrases' => [['phrase' => 'One', 'translations' => ['es-es' => 'Uno']], ['phrase' => 'Two']]]],
        ]], 'keys' => ['read' => ['project' => 'p1', 'type' => 'read']]],
    ],
];
