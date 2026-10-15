import { defineProject, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config';

export default mergeConfig(
  viteConfig,
  defineProject({
    test: {
      name: 'superadmin',
      environment: 'jsdom',
      // API ile aynı origin: jsdom XHR çapraz origin'de CORS ön isteği yapar
      environmentOptions: { jsdom: { url: 'http://localhost:8000/' } },
      setupFiles: ['../../packages/shared/src/test/setup.ts'],
      include: ['src/**/*.test.{ts,tsx}'],
      env: { VITE_API_URL: 'http://localhost:8000/api/v1' },
      testTimeout: 20000,
    },
  })
);