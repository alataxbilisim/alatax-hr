import { useMemo, useRef, useImperativeHandle, forwardRef } from 'react';
import ReactEChartsCore from 'echarts-for-react/lib/core';
import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import {
  GridComponent,
  TooltipComponent,
  LegendComponent,
  DatasetComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import type { EChartsOption } from 'echarts';
import { getReportChartThemeTokens } from './chartTheme';
import type { ReportChartMapping, ReportChartType, ReportRow } from './types';

echarts.use([
  BarChart,
  LineChart,
  PieChart,
  GridComponent,
  TooltipComponent,
  LegendComponent,
  DatasetComponent,
  CanvasRenderer,
]);

export interface ReportChartProps {
  type: ReportChartType;
  rows: ReportRow[];
  mapping: ReportChartMapping;
  height?: number | string;
  emptyLabel?: string;
  className?: string;
}

export interface ReportChartHandle {
  getPngDataUrl: () => string | null;
}

function toNumber(value: string | number | boolean | null): number {
  if (typeof value === 'number') return value;
  if (typeof value === 'boolean') return value ? 1 : 0;
  if (value === null || value === '') return 0;
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function buildOption(
  type: ReportChartType,
  rows: ReportRow[],
  mapping: ReportChartMapping
): EChartsOption {
  const theme = getReportChartThemeTokens();
  const { categoryField, valueField, seriesField } = mapping;

  const base: EChartsOption = {
    color: theme.colors,
    textStyle: { fontFamily: theme.fontFamily, color: theme.text },
    tooltip: { trigger: type === 'pie' ? 'item' : 'axis' },
    grid: { left: 48, right: 24, top: 40, bottom: 40, containLabel: true },
    legend: { textStyle: { color: theme.text } },
  };

  if (type === 'pie') {
    return {
      ...base,
      series: [
        {
          type: 'pie',
          radius: ['35%', '65%'],
          data: rows.map((row) => ({
            name: String(row[categoryField] ?? ''),
            value: toNumber(row[valueField] ?? null),
          })),
          label: { color: theme.text },
        },
      ],
    };
  }

  if (seriesField) {
    const categories = Array.from(
      new Set(rows.map((r) => String(r[categoryField] ?? '')))
    );
    const seriesKeys = Array.from(
      new Set(rows.map((r) => String(r[seriesField] ?? '')))
    );
    const series = seriesKeys.map((sk) => ({
      name: sk,
      type: type === 'line' || type === 'area' ? ('line' as const) : ('bar' as const),
      stack: type === 'stacked_bar' || type === 'area' ? 'total' : undefined,
      areaStyle: type === 'area' ? {} : undefined,
      data: categories.map((cat) => {
        const hit = rows.find(
          (r) =>
            String(r[categoryField] ?? '') === cat &&
            String(r[seriesField] ?? '') === sk
        );
        return toNumber(hit?.[valueField] ?? null);
      }),
    }));

    return {
      ...base,
      xAxis: {
        type: 'category',
        data: categories,
        axisLabel: { color: theme.text },
        axisLine: { lineStyle: { color: theme.border } },
      },
      yAxis: {
        type: 'value',
        axisLabel: { color: theme.text },
        splitLine: { lineStyle: { color: theme.border } },
      },
      series,
    };
  }

  const categories = rows.map((r) => String(r[categoryField] ?? ''));
  const values = rows.map((r) => toNumber(r[valueField] ?? null));

  if (type === 'line' || type === 'area') {
    return {
      ...base,
      xAxis: {
        type: 'category',
        data: categories,
        axisLabel: { color: theme.text },
        axisLine: { lineStyle: { color: theme.border } },
      },
      yAxis: {
        type: 'value',
        axisLabel: { color: theme.text },
        splitLine: { lineStyle: { color: theme.border } },
      },
      series: [
        {
          type: 'line',
          data: values,
          areaStyle: type === 'area' ? {} : undefined,
          smooth: true,
        },
      ],
    };
  }

  return {
    ...base,
    xAxis: {
      type: 'category',
      data: categories,
      axisLabel: { color: theme.text },
      axisLine: { lineStyle: { color: theme.border } },
    },
    yAxis: {
      type: 'value',
      axisLabel: { color: theme.text },
      splitLine: { lineStyle: { color: theme.border } },
    },
    series: [
      {
        type: 'bar',
        data: values,
        stack: type === 'stacked_bar' ? 'total' : undefined,
      },
    ],
  };
}

export const ReportChart = forwardRef<ReportChartHandle, ReportChartProps>(
  function ReportChart(
    { type, rows, mapping, height = 360, emptyLabel, className },
    ref
  ) {
    const chartRef = useRef<ReactEChartsCore | null>(null);

    useImperativeHandle(ref, () => ({
      getPngDataUrl: () => {
        const instance = chartRef.current?.getEchartsInstance();
        if (!instance) return null;
        return instance.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: 'transparent' });
      },
    }));

    const option = useMemo(
      () => buildOption(type, rows, mapping),
      [type, rows, mapping]
    );

    if (rows.length === 0) {
      return (
        <div
          className={className}
          style={{
            height,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: 'var(--text-tertiary)',
            fontSize: 'var(--fs-body)',
          }}
        >
          {emptyLabel ?? '—'}
        </div>
      );
    }

    return (
      <div className={className} style={{ width: '100%', height }}>
        <ReactEChartsCore
          ref={chartRef}
          echarts={echarts}
          option={option}
          style={{ height: '100%', width: '100%' }}
          notMerge
          lazyUpdate
        />
      </div>
    );
  }
);

ReportChart.displayName = 'ReportChart';
