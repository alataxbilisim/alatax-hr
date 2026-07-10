import type { PartialTheme } from '@nivo/theming';

// Nivo chart tema konfigürasyonu - dark/light tema ile uyumlu
export const getChartTheme = (isDark: boolean = true): PartialTheme => ({
  background: 'transparent',
  text: {
    fontSize: 12,
    fill: isDark ? '#94a3b8' : '#64748b',
    outlineWidth: 0,
    outlineColor: 'transparent',
  },
  axis: {
    domain: {
      line: {
        stroke: isDark ? '#334155' : '#e2e8f0',
        strokeWidth: 1,
      },
    },
    legend: {
      text: {
        fontSize: 12,
        fill: isDark ? '#cbd5e1' : '#475569',
        outlineWidth: 0,
        outlineColor: 'transparent',
      },
    },
    ticks: {
      line: {
        stroke: isDark ? '#334155' : '#e2e8f0',
        strokeWidth: 1,
      },
      text: {
        fontSize: 11,
        fill: isDark ? '#94a3b8' : '#64748b',
        outlineWidth: 0,
        outlineColor: 'transparent',
      },
    },
  },
  grid: {
    line: {
      stroke: isDark ? '#1e293b' : '#f1f5f9',
      strokeWidth: 1,
    },
  },
  legends: {
    title: {
      text: {
        fontSize: 12,
        fill: isDark ? '#cbd5e1' : '#475569',
        outlineWidth: 0,
        outlineColor: 'transparent',
      },
    },
    text: {
      fontSize: 11,
      fill: isDark ? '#94a3b8' : '#64748b',
      outlineWidth: 0,
      outlineColor: 'transparent',
    },
    ticks: {
      line: {},
      text: {
        fontSize: 10,
        fill: isDark ? '#94a3b8' : '#64748b',
        outlineWidth: 0,
        outlineColor: 'transparent',
      },
    },
  },
  annotations: {
    text: {
      fontSize: 13,
      fill: isDark ? '#cbd5e1' : '#475569',
      outlineWidth: 2,
      outlineColor: isDark ? '#0f172a' : '#ffffff',
      outlineOpacity: 1,
    },
    link: {
      stroke: isDark ? '#64748b' : '#94a3b8',
      strokeWidth: 1,
      outlineWidth: 2,
      outlineColor: isDark ? '#0f172a' : '#ffffff',
      outlineOpacity: 1,
    },
    outline: {
      stroke: isDark ? '#64748b' : '#94a3b8',
      strokeWidth: 2,
      outlineWidth: 2,
      outlineColor: isDark ? '#0f172a' : '#ffffff',
      outlineOpacity: 1,
    },
    symbol: {
      fill: isDark ? '#64748b' : '#94a3b8',
      outlineWidth: 2,
      outlineColor: isDark ? '#0f172a' : '#ffffff',
      outlineOpacity: 1,
    },
  },
  tooltip: {
    container: {
      background: isDark ? '#1e293b' : '#ffffff',
      color: isDark ? '#f1f5f9' : '#1e293b',
      fontSize: 12,
      borderRadius: 8,
      boxShadow: isDark 
        ? '0 4px 12px rgba(0,0,0,0.4)' 
        : '0 4px 12px rgba(0,0,0,0.15)',
      padding: '8px 12px',
    },
    basic: {},
    chip: {},
    table: {},
    tableCell: {},
    tableCellValue: {},
  },
});

// Renk şemaları
export const colorSchemes = {
  nivo: ['#10b981', '#06b6d4', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6', '#f97316'],
  category10: ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b', '#e377c2', '#7f7f7f'],
  accent: ['#7fc97f', '#beaed4', '#fdc086', '#ffff99', '#386cb0', '#f0027f', '#bf5b17', '#666666'],
  dark2: ['#1b9e77', '#d95f02', '#7570b3', '#e7298a', '#66a61e', '#e6ab02', '#a6761d', '#666666'],
  paired: ['#a6cee3', '#1f78b4', '#b2df8a', '#33a02c', '#fb9a99', '#e31a1c', '#fdbf6f', '#ff7f00'],
  pastel1: ['#fbb4ae', '#b3cde3', '#ccebc5', '#decbe4', '#fed9a6', '#ffffcc', '#e5d8bd', '#fddaec'],
  set1: ['#e41a1c', '#377eb8', '#4daf4a', '#984ea3', '#ff7f00', '#ffff33', '#a65628', '#f781bf'],
  set2: ['#66c2a5', '#fc8d62', '#8da0cb', '#e78ac3', '#a6d854', '#ffd92f', '#e5c494', '#b3b3b3'],
};

export type ColorScheme = keyof typeof colorSchemes;

export const getColors = (scheme: ColorScheme = 'nivo') => colorSchemes[scheme] || colorSchemes.nivo;

