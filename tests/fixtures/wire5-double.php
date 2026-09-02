<?php
/**
 * The smallest thing that can prove WIRE-5: a stand-in API.
 *
 * WIRE-5 asks that an integrator be able to point an EXISTING, unmodified
 * integration at a double. A test that asserts how the SDK composes a URL string
 * proves only that the string is composed; it cannot tell you the request was
 * ever issued, or that it arrived. So this is a real server, reached over a real
 * socket, and the assertion is on what it actually received.
 *
 * Records every request to $LANGSYS_WIRE5_LOG as one JSON object per line.
 */
$log = getenv('LANGSYS_WIRE5_LOG');

if ($log !== false) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];

    file_put_contents($log, json_encode([
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'host' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null,
        'has_auth' => isset($headers['X-Authorization']) || isset($headers['x-authorization']),
    ]) . "\n", FILE_APPEND);
}

header('Content-Type: application/json');
echo json_encode([
    'status' => true,
    'data' => ['key_type' => 'read', 'write_enabled' => false, 'base_locale' => 'en-us'],
]);
