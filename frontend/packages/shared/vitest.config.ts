import { defineProject } from 'vitest/config';

export default defineProject({
  esbuild: { jsx: 'automatic' },
  test: {
    name: 'shared',
    environment: 'jsdom',
    // API ile aynı origin: jsdom XHR çapraz origin'de CORS ön isteği yapar
    environmentOptions: { jsdom: { url: 'http://localhost:8000/' } },
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.test.{ts,tsx}'],
    env: { VITE_API_URL: 'http://localhost:8000/api/v1' },
  },
});
