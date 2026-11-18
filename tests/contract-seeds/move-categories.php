<?php

/*
 * The worlds of tests/Contract/MoveContractTest.php: content translated under
 * one category, which the code now renders under another, naming the first as
 * its previous category. Nothing in them reaches machine translation, so an
 * item the new category serves with a translation of its own got it from the
 * move.
 */

use Langsys\SDK\Html\HtmlParser;

$parser = new HtmlParser();

$moved = [
    'target_locales' => ['es-es'],
    'phrases' => [
        ['category' => 'Header', 'phrase' => 'Buy now', 'translations' => ['es-es' => 'Comprá ya']],
        ['category' => 'Header', 'phrase' => 'Sign in', 'translations' => ['es-es' => 'Ingresá']],
        ['category' => 'Header', 'phrase' => 'Add to cart', 'translations' => ['es-es' => 'Agregar al carrito']],
        ['category' => 'Header', 'phrase' => 'Not moved', 'translations' => ['es-es' => 'No movida']],
        ['category' => null, 'phrase' => 'Contact us', 'translations' => ['es-es' => 'Contáctanos']],
    ],
    'blocks' => [[
        'category' => 'Header',
        'custom_id' => $parser->generateCustomId('Header', ['Moved block', 'Second paragraph']),
        'content' => '<div><p>Moved block</p><p>Second paragraph</p></div>',
        'phrases' => [
            ['phrase' => 'Moved block', 'translations' => ['es-es' => 'Bloque movido']],
            ['phrase' => 'Second paragraph', 'translations' => ['es-es' => 'Segundo párrafo']],
        ],
    ]],
];

$group = [
    'target_locales' => ['es-es'],
    'phrases' => [
        ['category' => 'header', 'phrase' => 'Pay', 'translations' => ['es-es' => 'Pagar']],
        ['category' => 'header', 'phrase' => 'Back', 'translations' => ['es-es' => 'Volver']],
    ],
];

$messages = [
    'target_locales' => ['es-es'],
    'phrases' => [
        ['category' => 'Errors', 'phrase' => 'The name is required.', 'translations' => ['es-es' => 'El nombre es obligatorio.']],
        ['category' => 'Errors', 'phrase' => 'The email is invalid.', 'translations' => ['es-es' => 'El correo no es válido.']],
        ['category' => 'Errors', 'phrase' => 'The code expired.', 'translations' => ['es-es' => 'El código venció.']],
    ],
];

$keys = ['write' => ['project' => 'p1', 'type' => 'write'], 'read' => ['project' => 'p1', 'type' => 'read']];

return [
    'index' => 15,
    'runs' => 6,
    'worlds' => [
        'moved' => ['count' => 2, 'projects' => ['p1' => $moved], 'keys' => $keys],
        'renamed-group' => ['projects' => ['p1' => $group], 'keys' => $keys],
        'renamed-messages' => ['projects' => ['p1' => $messages], 'keys' => $keys],
    ],
];
