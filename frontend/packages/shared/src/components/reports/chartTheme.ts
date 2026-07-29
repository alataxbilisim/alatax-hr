/**
 * ECharts tema — theme.css CSS variable token'larından okunur.
 * Hardcode renk yok; dark/light tema otomatik yansır.
 */

function readCssVar(name: string, fallback: string): string {
  if (typeof window === 'undefined' || !window.getComputedStyle) {
    return fallback;
  }
  const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return value || fallback;
}

export function getReportChartColors(): string[] {
  return [
    readCssVar('--primary', '#6366f1'),
    readCssVar('--success', '#10b981'),
    readCssVar('--info', '#0ea5e9'),
    readCssVar('--warning', '#f59e0b'),
    readCssVar('--secondary', '#8b5cf6'),
    readCssVar('--danger', '#ef4444'),
    readCssVar('--neutral', '#94a3b8'),
  ];
}

export function getReportChartThemeTokens() {
  return {
    text: readCssVar('--text-secondary', '#94a3b8'),
    textPrimary: readCssVar('--text-primary', '#f8fafc'),
    border: readCssVar('--border-primary', 'rgba(255,255,255,0.08)'),
    bg: readCssVar('--bg-elevated', '#1c1c24'),
    fontFamily: readCssVar('--font-family', 'Inter, sans-serif'),
    colors: getReportChartColors(),
  };
}
