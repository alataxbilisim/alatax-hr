<?php

/**
 * PHPUnit bootstrap — config cache temizliği + test DB zorlaması.
 * Docker compose DB_DATABASE=alatax_hr ortam değişkenini ezer.
 * Yıkıcı migrate komutu yok.
 */

$autoload = dirname(__DIR__).'/vendor/autoload.php';
require $autoload;

$configCache = dirname(__DIR__).'/bootstrap/cache/config.php';
if (is_file($configCache)) {
    unlink($configCache);
}

// phpunit.xml force=true bazen artisan test in-process'te geç kalır; burada kesinleştir.
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

putenv('DB_CONNECTION=pgsql');
$_ENV['DB_CONNECTION'] = 'pgsql';
$_SERVER['DB_CONNECTION'] = 'pgsql';

putenv('DB_DATABASE=alatax_hr_testing');
$_ENV['DB_DATABASE'] = 'alatax_hr_testing';
$_SERVER['DB_DATABASE'] = 'alatax_hr_testing';

if ((getenv('DB_DATABASE') ?: '') !== 'alatax_hr_testing') {
    fwrite(STDERR, "[BOOTSTRAP] FATAL: DB_DATABASE could not be forced to alatax_hr_testing\n");
    exit(1);
}
