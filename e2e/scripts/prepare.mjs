#!/usr/bin/env node
/**
 * E2E hazırlığı: `*_e2e` veritabanı + `app-e2e` servisi + `php artisan e2e:reset`.
 * Canlı geliştirme veritabanına (dobedan_hr) hiçbir işlem yapmaz; sıfırlama komutu DB adı
 * `_e2e` ile bitmiyorsa reddeder.
 *
 * Usage: node scripts/prepare.mjs   (playwright globalSetup da bunu çağırır)
 */
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

function compose(args, options = {}) {
  return execFileSync('docker', ['compose', ...args], {
    cwd: ROOT,
    stdio: options.capture ? ['ignore', 'pipe', 'inherit'] : 'inherit',
    encoding: 'utf8',
  });
}

export function prepareE2eEnvironment() {
  const running = compose(['ps', '--status', 'running', '--services'], { capture: true }).split(/\r?\n/);
  if (!running.includes('postgres')) {
    throw new Error('Docker yığını ayakta değil: önce `docker compose up -d postgres redis` (veya tüm yığın).');
  }

  console.log('[e2e] veritabanı: <POSTGRES_DB>_e2e (yoksa oluşturulur)');
  compose([
    'exec',
    '-T',
    'postgres',
    'sh',
    '-c',
    'DB="${POSTGRES_DB}_e2e"; ' +
      'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = \'$DB\'" | grep -q 1 ' +
      '|| createdb -U "$POSTGRES_USER" "$DB"',
  ]);

  console.log('[e2e] app-e2e servisi başlatılıyor');
  compose(['--profile', 'e2e', 'up', '-d', '--wait', 'app-e2e']);

  console.log('[e2e] e2e:reset (migrate:fresh --seed --seeder=E2eSeeder)');
  compose(['exec', '-T', 'app-e2e', 'php', 'artisan', 'e2e:reset']);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  prepareE2eEnvironment();
}
