<?php
require __DIR__ . '/bootstrap.php';

use Langsys\SDK\Sync\Planner;
use Langsys\SDK\Sync\SourceScanner;

$hits = (new SourceScanner())->scan(file_get_contents(__DIR__ . '/app/views.php'), 'app/views.php');
$migration = ['files' => [__DIR__ . '/lang/en/checkout.php']];

// Offline: no key, no catalog - what a CI gate sees.
$offline = Planner::offline($hits, $migration, [], ['covered_groups' => ['validation']]);
echo 'offline: ', json_encode($offline->counts()), ' reported lines ', json_encode(array_column($offline->reported, 'line')), ' covered groups ', json_encode(array_column($offline->covered, 'group')), ' strict fails: ', json_encode($offline->failsStrict()), "\n";

// Against the catalog, with the Spanish files as existing translations.
$client = client(null, ['migration' => $migration]);
$plan = $client->planSync($hits, ['es-es' => ['files' => [__DIR__ . '/lang/es/checkout.php']]], ['covered_groups' => ['validation']]);
foreach ($plan->items as $item) {
    printf("  %-18s %-24s %s\n", $item['status'], $item['phrase'], json_encode($item['translations']));
}
echo json_encode($client->applySync($plan)), "\n";
echo 'stored: ', json_encode(array_map(function ($p) { return [$p['phrase'], $p['translations']]; }, state()['phrases'])), "\n";
