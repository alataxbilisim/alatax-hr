import type {
  ReportCatalogField,
  ReportConfigPayload,
  ReportQueryPayload,
  ReportRunResult,
  SavedReportPayload,
} from '@shared/services/api';

export function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function isReportCatalogField(value: unknown): value is ReportCatalogField {
  if (!isRecord(value)) return false;
  return typeof value.key === 'string' && typeof value.type === 'string';
}

export function isSavedReportPayload(value: unknown): value is SavedReportPayload {
  if (!isRecord(value)) return false;
  return typeof value.id === 'number' && typeof value.name === 'string';
}

export function isReportRunResult(value: unknown): value is ReportRunResult {
  if (!isRecord(value)) return false;
  return Array.isArray(value.rows) && isRecord(value.meta);
}

export function cellToExportValue(value: string | number | boolean | null): string | number | boolean {
  if (value === null) return '';
  return value;
}

export function buildQueryFromConfig(
  dataset: string,
  config: ReportConfigPayload,
  paging?: { limit?: number; offset?: number }
): ReportQueryPayload {
  return {
    dataset,
    fields: config.fields ?? [],
    filters: config.filters ?? [],
    group_by: config.group_by ?? [],
    aggregations: config.aggregations ?? [],
    sorts: config.sorts ?? [],
    joins: config.joins ?? [],
    limit: paging?.limit ?? 50,
    offset: paging?.offset ?? 0,
  };
}

/** Alan tipine göre izin verilen operatörler */
export function operatorsForFieldType(type: string): string[] {
  switch (type) {
    case 'number':
    case 'integer':
    case 'decimal':
    case 'money':
      return ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'in', 'is_null', 'is_not_null'];
    case 'date':
    case 'datetime':
      return ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'date_range', 'is_null', 'is_not_null'];
    case 'boolean':
      return ['eq', 'neq', 'is_null', 'is_not_null'];
    default:
      return ['eq', 'neq', 'like', 'in', 'is_null', 'is_not_null'];
  }
}

export type ListTab = 'mine' | 'shared' | 'system';

export function filterReportsByTab(
  items: SavedReportPayload[],
  tab: ListTab,
  currentUserId: number
): SavedReportPayload[] {
  switch (tab) {
    case 'mine':
      return items.filter((r) => r.user_id === currentUserId && !r.is_system);
    case 'system':
      return items.filter((r) => r.is_system);
    case 'shared':
      return items.filter(
        (r) =>
          !r.is_system &&
          r.user_id !== currentUserId &&
          (r.is_shared ||
            (Array.isArray(r.share_user_ids) && r.share_user_ids.includes(currentUserId)) ||
            (Array.isArray(r.share_role_ids) && r.share_role_ids.length > 0))
      );
    default:
      return items;
  }
}
