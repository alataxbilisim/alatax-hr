import { setupServer } from 'msw/node';

/** Testlerin ortak MSW sunucusu; handler'lar test içinde `server.use(...)` ile eklenir. */
export const server = setupServer();
