<?php
require __DIR__ . '/bootstrap.php';

$client = client();
echo $client->translatePage('<html><body><h1>Hello</h1><p>Read the <b>terms</b> first.</p></body></html>'), "\n";

$client->flushPendingRegistrations();
$state = state();
echo 'phrases: ', json_encode(array_column($state['phrases'], 'phrase')), "\n";
echo 'blocks:  ', json_encode(array_map(function ($b) { return [$b['custom_id'], array_column($b['phrases'], 'phrase')]; }, $state['blocks'])), "\n";
