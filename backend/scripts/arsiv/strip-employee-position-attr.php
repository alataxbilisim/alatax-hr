<?php

$files = [
    'tests/Feature/Reports/DatasetGroupedQueryTest.php',
    'tests/Feature/SalaryBandTest.php',
    'tests/Feature/ReportPivotAndMeasureTest.php',
    'tests/Feature/PortalProfileContractTest.php',
    'tests/Feature/AuditWave1Test.php',
    'scripts/uzun-kosu/seed-scale.php',
    'scripts/denetim/run-denetim.php',
];

$root = dirname(__DIR__);
foreach ($files as $rel) {
    $path = $root.'/'.$rel;
    if (! is_file($path)) {
        echo "MISS {$rel}\n";

        continue;
    }
    $s = file_get_contents($path);
    $orig = $s;
    $s = preg_replace("/^(\\s*)'position'\\s*=>\\s*'[^']*',\\s*$/m", "$1// position string dropped\n", $s);
    $s = preg_replace("/^(\\s*)'position'\\s*=>\\s*null,\\s*$/m", "$1'position_id' => null,\n", $s);
    $s = preg_replace("/^(\\s*)'position'\\s*=>\\s*\\\$[a-zA-Z0-9_\\->\\(\\)]+,\\s*$/m", "$1// position string dropped\n", $s);
    // $emp->update([..., 'position' => ...])
    $s = preg_replace("/,\\s*'position'\\s*=>\\s*[^,\\]]+/", '', $s);
    if ($s !== $orig) {
        file_put_contents($path, $s);
        echo "UPDATED {$rel}\n";
    } else {
        echo "NOCHANGE {$rel}\n";
    }
}
