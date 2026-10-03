<?php
require __DIR__ . '/bootstrap.php';

$client = client(null, ['migration' => ['files' => [__DIR__ . '/lang/en/checkout.php']]]);
echo $client->translate('checkout.submit'), "\n";
echo $client->translate('checkout.total', null, '__uncategorized__', null, ['amount' => '12 €']), "\n";
$entry = $client->resolveLegacyKey('checkout.total');
echo json_encode([$entry['phrase'], $entry['category'], $entry['key']]), "\n";
