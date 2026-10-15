import { prepareE2eEnvironment } from './scripts/prepare.mjs';

export default async function globalSetup(): Promise<void> {
  if (process.env.E2E_SKIP_PREPARE === '1') {
    console.log('[e2e] E2E_SKIP_PREPARE=1 — veritabanı sıfırlaması atlandı');
    return;
  }
  prepareE2eEnvironment();
}
