import { defineConfig } from 'vitest/config';

/** Kök Vitest yapılandırması: projeler + birleşik kapsam (`pnpm test`, `pnpm test:coverage`). */
export default defineConfig({
  test: {
    projects: ['packages/shared', 'apps/superadmin', 'apps/company', 'apps/portal'],
    coverage: {
      provider: 'v8',
      reporter: ['text-summary', 'json-summary', 'html', 'lcov'],
      reportsDirectory: './coverage',
      include: ['apps/*/src/**/*.{ts,tsx}', 'packages/shared/src/**/*.{ts,tsx}'],
      exclude: ['**/*.test.{ts,tsx}', '**/test/**', '**/*.d.ts', '**/main.tsx'],
    },
  },
});
