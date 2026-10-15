import type { RequestHandler } from 'msw';

/**
 * Route duman testinde boş liste yerine nesne bekleyen uçların "boş ama geçerli" yanıtları.
 * Genel geri dönüş (`emptyApiFallbackHandlers`) GET için boş sayfalı liste döner.
 */
export const portalSmokeHandlers: RequestHandler[] = [];
