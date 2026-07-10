import React from 'react';
import { ResponsiveHeatMap } from '@nivo/heatmap';
import { getChartTheme, ColorScheme } from './chartTheme';
import type { ChartDataItem } from '../types';

interface HeatmapWidgetProps {
  data: ChartDataItem[];
  colorScheme?: ColorScheme;
  showLegend?: boolean;
  isDark?: boolean;
  measureLabel?: string;
}

const HeatmapWidget: React.FC<HeatmapWidgetProps> = ({
  data,
  showLegend = true,
  isDark = true,
  measureLabel = 'Değer',
}) => {
  const theme = getChartTheme(isDark);

  if (data.length === 0) {
    return (
      <div className="chart-empty-state">
        <p>Veri bulunamadı</p>
      </div>
    );
  }

  // Nivo v0.87+ için yeni heatmap veri formatı
  // Format: [{ id: 'row', data: [{ x: 'col', y: value }] }]
  const maxValue = Math.max(...data.map(d => d.value));
  const minValue = Math.min(...data.map(d => d.value));

  const heatmapData = [
    {
      id: measureLabel,
      data: data.map(item => ({
        x: item.label,
        y: item.value,
      })),
    },
  ];

  return (
    <div style={{ height: '100%', minHeight: 400 }}>
      <ResponsiveHeatMap
        data={heatmapData}
        margin={{ top: 60, right: 90, bottom: 60, left: 90 }}
        valueFormat=" >-.2s"
        axisTop={{
          tickSize: 5,
          tickPadding: 5,
          tickRotation: -45,
          legend: '',
          legendOffset: 46,
          truncateTickAt: 0,
        }}
        axisRight={null}
        axisLeft={{
          tickSize: 5,
          tickPadding: 5,
          tickRotation: 0,
          legend: measureLabel,
          legendPosition: 'middle',
          legendOffset: -72,
          truncateTickAt: 0,
        }}
        colors={{
          type: 'sequential',
          scheme: 'blues',
          minValue: minValue,
          maxValue: maxValue,
        }}
        emptyColor="#555555"
        borderColor={{
          from: 'color',
          modifiers: [['darker', 0.8]],
        }}
        labelTextColor={{
          from: 'color',
          modifiers: [['darker', 1.8]],
        }}
        legends={showLegend ? [
          {
            anchor: 'bottom',
            translateX: 0,
            translateY: 30,
            length: 400,
            thickness: 8,
            direction: 'row',
            tickPosition: 'after',
            tickSize: 3,
            tickSpacing: 4,
            tickOverlap: false,
            tickFormat: '>-.2s',
            title: measureLabel,
            titleAlign: 'start',
            titleOffset: 4,
          },
        ] : []}
        theme={theme}
        animate={true}
        motionConfig="gentle"
        hoverTarget="cell"
        inactiveOpacity={0.25}
      />
    </div>
  );
};

export default HeatmapWidget;

