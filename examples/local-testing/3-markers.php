<?php
require __DIR__ . '/bootstrap.php';

foreach (['Ana', 'Luis'] as $name) {
    $client = client();
    echo $client->translatePage("<html><body><p>Welcome back, <!--ls:name-->$name<!--/ls--></p></body></html>"), "\n";
    $client->flushPendingRegistrations();
}
echo 'phrases: ', json_encode(array_column(state()['phrases'], 'phrase')), "\n";
