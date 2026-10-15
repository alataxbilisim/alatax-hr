import type { RequestHandler } from 'msw';
import { apiOk, companyAdminUser } from '@shared/test';

/**
 * Route duman testinde boş liste yerine nesne bekleyen uçların "boş ama geçerli" yanıtları.
 * Genel geri dönüş (`emptyApiFallbackHandlers`) GET için boş sayfalı liste döner.
 */
export const companySmokeHandlers: RequestHandler[] = [
  apiOk('get', '/users/:id', companyAdminUser()),
  apiOk('get', '/recruitment/reports/summary', {
    total_positions: 0,
    active_positions: 0,
    total_applications: 0,
    total_interviews: 0,
    hired: 0,
    rejected: 0,
    conversion_rate: 0,
    interview_rate: 0,
    applications_by_status: {},
    period: { start_date: null, end_date: null },
  }),
];
