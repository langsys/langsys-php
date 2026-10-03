<?php
require __DIR__ . '/bootstrap.php';

$client = client(null, ['migration' => ['files' => [__DIR__ . '/lang/en/checkout.php']]]);
echo json_encode($client->importLegacyTranslations(['es-es' => ['files' => [__DIR__ . '/lang/es/checkout.php']]])), "\n";

// The next catalog read serves the imported translations.
$next = client();
echo json_encode($next->getTranslations('es-es')['checkout']), "\n";

try {
    $client->importLegacyTranslations(['it-it' => ['files' => [__DIR__ . '/lang/es/checkout.php']]]);
} catch (Langsys\SDK\Exception\LangsysException $e) {
    echo 'refused: ', $e->getMessage(), "\n";
}
