<?php

$files = glob(__DIR__.'/../database/migrations/*.php');
$out = [];

foreach ($files as $f) {
    $c = file_get_contents($f);
    if (! preg_match_all('/\$table->enum\(\s*\'([^\']+)\'\s*,\s*\[(.*?)\]\s*\)/s', $c, $m, PREG_SET_ORDER)) {
        continue;
    }
    foreach ($m as $match) {
        $vals = preg_replace('/\s+/', ' ', trim($match[2]));
        $out[] = [
            'file' => basename($f),
            'column' => $match[1],
            'values' => $vals,
        ];
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
echo 'COUNT='.count($out)."\n";

// Unique value sets
$sets = [];
foreach ($out as $row) {
    $sets[$row['values']][] = $row['file'].'::'.$row['column'];
}
echo 'UNIQUE_SETS='.count($sets)."\n";
foreach ($sets as $vals => $usages) {
    echo "\n---\n{$vals}\n  ".implode("\n  ", $usages)."\n";
}
