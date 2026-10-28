<?php
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/department_map.php';

header('Content-Type: text/plain');

$updated = 0;
$skipped = 0;
$unmapped = [];

foreach ($db->collection('reports')->documents() as $doc) {
    if (!$doc->exists()) continue;
    $data = $doc->data();

    if (!empty($data['departmentId'])) {
        echo "SKIP {$doc->id()} → {$data['departmentId']}\n";
        $skipped++;
        continue;
    }

    $categoryId = trim($data['category'] ?? '');
    $dept       = resolve_department($db, $categoryId);

    if ($dept === null) {
        $unmapped[] = "{$doc->id()} (category='{$categoryId}')";
        echo "NO-MAP {$doc->id()} — category '{$categoryId}' has no departmentId\n";
        continue;
    }

    $db->collection('reports')->document($doc->id())->set(
        ['departmentId' => $dept],
        ['merge' => true]
    );

    echo "SET {$doc->id()} ('{$categoryId}') → {$dept}\n";
    $updated++;
}

echo "\nDone. updated={$updated} skipped={$skipped}\n";

if ($unmapped) {
    echo "\nUnmapped reports:\n";
    foreach ($unmapped as $u) echo "  - {$u}\n";
}