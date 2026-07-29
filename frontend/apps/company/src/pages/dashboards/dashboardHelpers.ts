import type {
  DashboardPayload,
  DashboardRunResult,
  DashboardWidgetDef,
  DashboardWidgetRunResult,
  DashboardWidgetType,
} from '@shared/services/api';

const WIDGET_TYPES: DashboardWidgetType[] = ['kpi', 'chart', 'table', 'pivot', 'text'];

export type DashboardListTab = 'mine' | 'shared' | 'system';

export function isDashboardPayload(value: unknown): value is DashboardPayload {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return false;
  if (!('id' in value) || !('name' in value) || !('layout' in value)) return false;
  return typeof value.id === 'number' && typeof value.name === 'string';
}

export function isDashboardRunResult(value: unknown): value is DashboardRunResult {
  if (typeof value !== 'object' || value === null) return false;
  if (!('widgets' in value)) return false;
  return Array.isArray(value.widgets);
}

export function parseWidgetType(value: string): DashboardWidgetType {
  for (const t of WIDGET_TYPES) {
    if (t === value) return t;
  }
  return 'text';
}

export function isWidgetType(value: unknown): value is DashboardWidgetType {
  if (typeof value !== 'string') return false;
  for (const t of WIDGET_TYPES) {
    if (t === value) return true;
  }
  return false;
}

export function widgetsFromDashboard(d: DashboardPayload): DashboardWidgetDef[] {
  const widgets = d.layout?.widgets;
  if (!Array.isArray(widgets)) return [];
  return widgets.filter((w): w is DashboardWidgetDef => {
    if (typeof w !== 'object' || w === null) return false;
    return typeof w.id === 'string' && isWidgetType(w.type);
  });
}

export function filterDashboardsByTab(
  items: DashboardPayload[],
  tab: DashboardListTab,
  userId: number
): DashboardPayload[] {
  if (tab === 'system') return items.filter((d) => d.is_system);
  if (tab === 'mine') return items.filter((d) => !d.is_system && d.owner_id === userId);
  return items.filter((d) => !d.is_system && d.owner_id !== userId);
}

export function resultByWidgetId(
  run: DashboardRunResult | null
): Map<string, DashboardWidgetRunResult> {
  const map = new Map<string, DashboardWidgetRunResult>();
  if (!run) return map;
  for (const w of run.widgets) {
    map.set(w.id, w);
  }
  return map;
}

export function formatRanAt(iso: string | undefined, locale: string): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
}

export function minPollIntervalMs(widgets: DashboardWidgetDef[]): number | null {
  let min = Infinity;
  for (const w of widgets) {
    const sec = w.refresh_interval ?? 0;
    if (sec >= 30 && sec < min) min = sec;
  }
  if (!Number.isFinite(min)) return null;
  return min * 1000;
}

export interface GlobalFilterValues {
  date_preset?: string;
  hire_date_from?: string;
  hire_date_to?: string;
  department_id?: string;
  branch_id?: string;
  [key: string]: string | undefined;
}

export function valuesToRunPayload(values: GlobalFilterValues): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  const from = values.hire_date_from;
  const to = values.hire_date_to;
  if (from && to) {
    out.hire_date = { from, to };
  }
  if (values.department_id) {
    const n = Number(values.department_id);
    if (Number.isFinite(n)) out.department_id = n;
  }
  if (values.branch_id) {
    const n = Number(values.branch_id);
    if (Number.isFinite(n)) out.branch_id = n;
  }
  for (const [k, v] of Object.entries(values)) {
    if (
      k === 'date_preset' ||
      k === 'hire_date_from' ||
      k === 'hire_date_to' ||
      k === 'department_id' ||
      k === 'branch_id'
    ) {
      continue;
    }
    if (v !== undefined && v !== '') out[k] = v;
  }
  return out;
}

export function datePresetRange(preset: string): { from: string; to: string } | null {
  const now = new Date();
  const y = now.getFullYear();
  const m = now.getMonth();
  const pad = (n: number) => String(n).padStart(2, '0');
  const iso = (d: Date) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  if (preset === 'this_month') {
    return { from: iso(new Date(y, m, 1)), to: iso(new Date(y, m + 1, 0)) };
  }
  if (preset === 'last_month') {
    return { from: iso(new Date(y, m - 1, 1)), to: iso(new Date(y, m, 0)) };
  }
  if (preset === 'this_year') {
    return { from: iso(new Date(y, 0, 1)), to: iso(new Date(y, 11, 31)) };
  }
  return null;
}

export function isReportRows(data: unknown): data is { rows: Record<string, unknown>[]; meta?: unknown } {
  if (typeof data !== 'object' || data === null) return false;
  if (!('rows' in data)) return false;
  return Array.isArray(data.rows);
}

export function isKpiData(data: unknown): data is { value: unknown; direction?: string | null } {
  if (typeof data !== 'object' || data === null) return false;
  return 'value' in data;
}

export function isTextData(data: unknown): data is { content: string } {
  if (typeof data !== 'object' || data === null) return false;
  return 'content' in data && typeof data.content === 'string';
}

export function isPivotLike(data: unknown): boolean {
  if (typeof data !== 'object' || data === null) return false;
  return 'row_keys' in data && 'cells' in data;
}
