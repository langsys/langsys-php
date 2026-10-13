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

// No key: an unconfigured client renders the source with its values filled and
// sends nothing; a write says why it cannot. With require_credentials on, the
// first use throws instead.
$quiet = ['cache' => new Langsys\SDK\Cache\NullCache(), 'warn_runtime_requirements' => false, 'error_log' => false];
$unconfigured = new Langsys\SDK\Client('', 'p1', $quiet);
echo 'no key: ', $unconfigured->translate('Welcome, {name}', 'es-es', null, null, ['name' => 'Ana']), "\n";
try {
    $unconfigured->registerPhrases(['Hello']);
} catch (Langsys\SDK\Exception\ConfigurationException $e) {
    echo 'no key, a write: ', $e->getMessage(), "\n";
}
try {
    (new Langsys\SDK\Client('', 'p1', $quiet + ['require_credentials' => true]))->translate('Hello', 'es-es');
} catch (Langsys\SDK\Exception\ConfigurationException $e) {
    echo 'no key, required: ', $e->getMessage(), "\n";
}
