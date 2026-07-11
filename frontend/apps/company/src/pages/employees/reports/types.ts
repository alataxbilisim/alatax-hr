// Dashboard ve Widget tipleri

export type WidgetType = 'chart' | 'kpi' | 'table' | 'treemap' | 'text';
export type ChartType = 'bar' | 'pie' | 'line' | 'heatmap';

export interface WidgetLayout {
  x: number;
  y: number;
  w: number;
  h: number;
  minW?: number;
  minH?: number;
  maxW?: number;
  maxH?: number;
}

export interface WidgetConfig {
  dimension?: string;
  measure?: string;
  chartType?: ChartType;
  filters?: Record<string, unknown>;
  // KPI specific
  kpiType?: 'count' | 'sum' | 'avg' | 'min' | 'max';
  // Text specific
  content?: string;
}

export interface DashboardWidget {
  id: string;
  type: WidgetType;
  title: string;
  notes?: string;
  labels?: string[];
  config: WidgetConfig;
  layout: WidgetLayout;
}

export interface Dashboard {
  id?: number;
  name: string;
  description?: string;
  widgets: DashboardWidget[];
  layout_config?: {
    cols?: number;
    rowHeight?: number;
  };
  is_favorite?: boolean;
  is_shared?: boolean;
  user?: {
    id: number;
    name: string;
  };
  created_at?: string;
  updated_at?: string;
}

/** Kanonik grafik/tablo satır tipi (chart widget'lar + factory). */
export interface ChartDataItem {
  id: string | number;
  label: string;
  value: number;
}

/** @deprecated ChartDataItem kullanın — geriye uyumluluk alias'ı */
export type WidgetDataItem = ChartDataItem;

export interface KPIData {
  total: number;
  average?: number;
  min?: number;
  max?: number;
  sum?: number;
  label?: string;
}

// Metadata types
export interface DimensionOption {
  value: string;
  label: string;
}

export interface MeasureOption {
  value: string;
  label: string;
}

export interface ReportMetadata {
  dimensions: DimensionOption[];
  measures: MeasureOption[];
}

// Default widget templates
export const DEFAULT_WIDGET_LAYOUTS: Record<WidgetType, Partial<WidgetLayout>> = {
  chart: { w: 6, h: 4, minW: 3, minH: 3 },
  kpi: { w: 3, h: 2, minW: 2, minH: 1, maxH: 2 },
  table: { w: 6, h: 4, minW: 4, minH: 3 },
  treemap: { w: 6, h: 4, minW: 4, minH: 3 },
  text: { w: 3, h: 2, minW: 2, minH: 1 },
};

export const WIDGET_TYPE_LABELS: Record<WidgetType, string> = {
  chart: 'Grafik',
  kpi: 'KPI Kartı',
  table: 'Veri Tablosu',
  treemap: 'Treemap',
  text: 'Metin/Not',
};

export const CHART_TYPE_LABELS: Record<ChartType, string> = {
  bar: 'Çubuk Grafik',
  pie: 'Pasta Grafik',
  line: 'Çizgi Grafik',
  heatmap: 'Isı Haritası',
};

