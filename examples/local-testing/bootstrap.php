<?php
/**
 * Shared by every check. Against the API double (the default) each check
 * starts from seed.json; against a local Langsys nothing is seeded and the
 * project, key and API URL come from the environment.
 */
require __DIR__ . '/vendor/autoload.php';

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;

define('DOUBLE', getenv('LANGSYS_API_URL') === false ? (getenv('LANGSYS_DOUBLE') ?: 'http://127.0.0.1:8791') : null);
define('API', DOUBLE === null ? getenv('LANGSYS_API_URL') : DOUBLE . '/api');
define('PROJECT', DOUBLE === null ? getenv('LANGSYS_PROJECT_ID') : 'p1');

if (DOUBLE !== null) {
    file_get_contents(DOUBLE . '/__fixture/seed', false, stream_context_create(['http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => file_get_contents(__DIR__ . '/seed.json'),
    ]]));
}

function client($key = null, array $options = [])
{
    $key = $key !== null ? $key : (DOUBLE === null ? getenv('LANGSYS_API_KEY') : 'local-write');
    $client = new Client($key, PROJECT, $options + ['api_url' => API, 'cache' => new NullCache(), 'error_log' => false]);
    $client->setLocale('es-es');

    return $client;
}

/**
 * What the double accepted. A local Langsys has no such route: read the
 * Translation Manager instead.
 */
function state()
{
    if (DOUBLE === null) {
        return ['phrases' => [], 'blocks' => []];
    }

    return json_decode(file_get_contents(DOUBLE . '/__fixture/state'), true)['projects']['p1'];
}
