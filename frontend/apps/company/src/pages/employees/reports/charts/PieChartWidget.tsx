import React from 'react';
import { ResponsivePie } from '@nivo/pie';
import { getChartTheme, getColors, ColorScheme } from './chartTheme';
import type { ChartDataItem } from '../types';

interface PieChartWidgetProps {
  data: ChartDataItem[];
  innerRadius?: number; // 0 = pie, 0.5 = donut
  colorScheme?: ColorScheme;
  showLegend?: boolean;
  showDataLabels?: boolean;
  isDark?: boolean;
}

const PieChartWidget: React.FC<PieChartWidgetProps> = ({
  data,
  innerRadius = 0,
  colorScheme = 'nivo',
  showLegend = true,
  showDataLabels = true,
  isDark = true,
}) => {
  const theme = getChartTheme(isDark);
  const colors = getColors(colorScheme);

  // Nivo pie formatına dönüştür
  const chartData = data.map((item, index) => ({
    id: item.label,
    label: item.label,
    value: item.value,
    color: colors[index % colors.length],
  }));

  if (data.length === 0) {
    return (
      <div className="chart-empty-state">
        <p>Veri bulunamadı</p>
      </div>
    );
  }

  return (
    <div style={{ height: '100%', minHeight: 300 }}>
      <ResponsivePie
        data={chartData}
        margin={{ top: 40, right: showLegend ? 160 : 40, bottom: 40, left: 40 }}
        innerRadius={innerRadius}
        padAngle={0.7}
        cornerRadius={4}
        activeOuterRadiusOffset={8}
        colors={({ data }) => data.color}
        borderWidth={1}
        borderColor={{ from: 'color', modifiers: [['darker', 0.2]] }}
        enableArcLinkLabels={showDataLabels}
        arcLinkLabelsSkipAngle={10}
        arcLinkLabelsTextColor={isDark ? '#94a3b8' : '#64748b'}
        arcLinkLabelsThickness={2}
        arcLinkLabelsColor={{ from: 'color' }}
        enableArcLabels={showDataLabels}
        arcLabelsSkipAngle={10}
        arcLabelsTextColor={{ from: 'color', modifiers: [['darker', 2]] }}
        legends={showLegend ? [
          {
            anchor: 'right',
            direction: 'column',
            justify: false,
            translateX: 100,
            translateY: 0,
            itemsSpacing: 4,
            itemWidth: 100,
            itemHeight: 18,
            itemTextColor: isDark ? '#94a3b8' : '#64748b',
            itemDirection: 'left-to-right',
            itemOpacity: 1,
            symbolSize: 12,
            symbolShape: 'circle',
          }
        ] : []}
        theme={theme}
        animate={true}
        motionConfig="gentle"
      />
    </div>
  );
};

export default PieChartWidget;

