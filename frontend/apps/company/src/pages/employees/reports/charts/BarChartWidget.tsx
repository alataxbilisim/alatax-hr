import React from 'react';
import { ResponsiveBar } from '@nivo/bar';
import { getChartTheme, getColors, ColorScheme } from './chartTheme';
import type { ChartDataItem } from '../types';

export type { ChartDataItem };

interface BarChartWidgetProps {
  data: ChartDataItem[];
  layout?: 'vertical' | 'horizontal';
  colorScheme?: ColorScheme;
  showLegend?: boolean;
  showDataLabels?: boolean;
  isDark?: boolean;
  measureLabel?: string;
}

const BarChartWidget: React.FC<BarChartWidgetProps> = ({
  data,
  layout = 'vertical',
  colorScheme = 'nivo',
  showLegend = false,
  showDataLabels = true,
  isDark = true,
  measureLabel = 'Değer',
}) => {
  const theme = getChartTheme(isDark);
  const colors = getColors(colorScheme);

  // Nivo bar formatına dönüştür
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
      <ResponsiveBar
        data={chartData}
        keys={['value']}
        indexBy="label"
        margin={{ 
          top: 20, 
          right: showLegend ? 120 : 20, 
          bottom: layout === 'vertical' ? 80 : 40, 
          left: layout === 'horizontal' ? 140 : 60 
        }}
        padding={0.3}
        layout={layout}
        valueScale={{ type: 'linear' }}
        indexScale={{ type: 'band', round: true }}
        colors={({ index }) => colors[index % colors.length]}
        borderRadius={4}
        borderColor={{ from: 'color', modifiers: [['darker', 1.6]] }}
        axisTop={null}
        axisRight={null}
        axisBottom={{
          tickSize: 5,
          tickPadding: 5,
          tickRotation: layout === 'vertical' && data.length > 6 ? -45 : 0,
          legend: layout === 'vertical' ? '' : measureLabel,
          legendPosition: 'middle',
          legendOffset: 36,
          truncateTickAt: 0,
        }}
        axisLeft={{
          tickSize: 5,
          tickPadding: 5,
          tickRotation: 0,
          legend: layout === 'vertical' ? measureLabel : '',
          legendPosition: 'middle',
          legendOffset: -50,
          truncateTickAt: 0,
        }}
        enableLabel={showDataLabels}
        label={d => `${d.value}`}
        labelSkipWidth={12}
        labelSkipHeight={12}
        labelTextColor={{ from: 'color', modifiers: [['darker', 2]] }}
        legends={showLegend ? [
          {
            dataFrom: 'indexes',
            anchor: 'bottom-right',
            direction: 'column',
            justify: false,
            translateX: 120,
            translateY: 0,
            itemsSpacing: 2,
            itemWidth: 100,
            itemHeight: 20,
            itemDirection: 'left-to-right',
            itemOpacity: 0.85,
            symbolSize: 12,
            symbolShape: 'circle',
          }
        ] : []}
        theme={theme}
        animate={true}
        motionConfig="gentle"
        role="application"
        ariaLabel="Bar chart"
      />
    </div>
  );
};

export default BarChartWidget;

