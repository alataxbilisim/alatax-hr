// Legacy exports (backward compatibility)
export { default as ReportBuilder } from './ReportBuilder';
export type { ReportConfig } from './ReportBuilder';
export { default as ReportPreview } from './ReportPreview';
export { default as SavedReportsList } from './SavedReportsList';
export type { SavedReport } from './SavedReportsList';
export { default as SaveReportModal } from './SaveReportModal';
export * from './charts';

// New Dashboard exports
export { default as DashboardGrid } from './DashboardGrid';
export { default as WidgetFactory } from './WidgetFactory';
export { default as AddWidgetModal } from './AddWidgetModal';
export { default as WidgetSettingsPanel } from './WidgetSettingsPanel';
export { default as SaveDashboardModal } from './SaveDashboardModal';
export { default as DashboardList } from './DashboardList';
export { default as ExportMenu } from './ExportMenu';
export * from './types';
export * from './widgets';
