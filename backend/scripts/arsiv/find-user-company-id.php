<?php

$roots = [__DIR__.'/../app', __DIR__.'/../database', __DIR__.'/../tests'];
$patterns = [
    "/User::(?:query\(\)->)?where\(['\"]company_id['\"]/",
    "/->where\(['\"]company_id['\"].{0,80}users/",
    '/user:id,name,email,company_id/',
    "/'company_id',\s*'preferences'/",
    "/DB::table\(['\"]users['\"]\).*company_id/s",
];

foreach ($roots as $root) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $src = file_get_contents($path);
        foreach ($patterns as $p) {
            if (preg_match_all($p, $src, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $line = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
                    echo str_replace('\\', '/', substr($path, strlen(dirname(__DIR__)) + 1)).":{$line}: {$hit[0]}\n";
                }
            }
        }
        // also: where('company_id' on lines near User::
        if (preg_match_all('/where\([\'\"]company_id[\'\"]/', $src, $wm, PREG_OFFSET_CAPTURE)) {
            foreach ($wm[0] as $hit) {
                $start = max(0, $hit[1] - 200);
                $ctx = substr($src, $start, 400);
                if (preg_match('/User::|from\([\'\"]users[\'\"]\)|table\([\'\"]users[\'\"]\)/', $ctx)) {
                    $line = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
                    echo str_replace('\\', '/', substr($path, strlen(dirname(__DIR__)) + 1)).":{$line}: context-User where company_id\n";
                }
            }
        }
    }
}
