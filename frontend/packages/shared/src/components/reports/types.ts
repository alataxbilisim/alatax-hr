/** Rapor motoru (D1b) — paylaşılan görselleştirme tipleri */

export type ReportChartType = 'bar' | 'line' | 'pie' | 'area' | 'stacked_bar';

export type ReportViewMode = 'table' | 'chart';

export interface ReportChartMapping {
  type: ReportChartType;
  /** Kategori / X ekseni alanı */
  categoryField: string;
  /** Değer / Y ekseni alanı */
  valueField: string;
  /** Stacked / seri ayrımı (opsiyonel) */
  seriesField?: string;
}

export interface ReportTableColumn {
  id: string;
  header: string;
  accessorKey: string;
  size?: number;
}

export type ReportRow = Record<string, string | number | boolean | null>;
