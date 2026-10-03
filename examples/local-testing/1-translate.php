<?php
require __DIR__ . '/bootstrap.php';

$client = client();
echo $client->translate('Hello'), "\n";                       // from the catalog
echo json_encode($client->resolve('Welcome, {name}', null, null, ['name' => 'Ana'])), "\n";

// The chain with no catalog: an unreachable API, a lang-file fallback, never an exception.
$offline = client(null, ['api_url' => 'http://127.0.0.1:9/api']);
$offline->useMissFallback(function ($phrase) {
    return $phrase === 'Welcome, {name}' ? 'Bienvenida, {name}' : null;
});
echo json_encode($offline->resolve('Welcome, {name}', null, null, ['name' => 'Ana'])), "\n";
echo json_encode($offline->resolve('Not in the files')), "\n";

// No key: the core refuses at construction; a framework binding builds no client then.
try {
    new Langsys\SDK\Client('', 'p1', ['cache' => new Langsys\SDK\Cache\NullCache()]);
} catch (Langsys\SDK\Exception\LangsysException $e) {
    echo 'no key: ', $e->getMessage(), "\n";
}
