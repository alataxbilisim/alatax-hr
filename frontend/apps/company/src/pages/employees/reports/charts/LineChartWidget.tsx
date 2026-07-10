import React from 'react';
import { ResponsiveLine } from '@nivo/line';
import { getChartTheme, getColors, ColorScheme } from './chartTheme';
import type { ChartDataItem } from '../types';

interface LineChartWidgetProps {
  data: ChartDataItem[];
  colorScheme?: ColorScheme;
  showLegend?: boolean;
  showPoints?: boolean;
  enableArea?: boolean;
  isDark?: boolean;
  measureLabel?: string;
}

const LineChartWidget: React.FC<LineChartWidgetProps> = ({
  data,
  colorScheme = 'nivo',
  showLegend = false,
  showPoints = true,
  enableArea = false,
  isDark = true,
  measureLabel = 'Değer',
}) => {
  const theme = getChartTheme(isDark);
  const colors = getColors(colorScheme);

  // Nivo line formatına dönüştür - tek seri
  const chartData = [
    {
      id: measureLabel,
      color: colors[0],
      data: data.map(item => ({
        x: item.label,
        y: item.value,
      })),
    },
  ];

  if (data.length === 0) {
    return (
      <div className="chart-empty-state">
        <p>Veri bulunamadı</p>
      </div>
    );
  }

  return (
    <div style={{ height: '100%', minHeight: 300 }}>
      <ResponsiveLine
        data={chartData}
        margin={{ top: 20, right: showLegend ? 110 : 20, bottom: 80, left: 60 }}
        xScale={{ type: 'point' }}
        yScale={{
          type: 'linear',
          min: 0,
          max: 'auto',
          stacked: false,
          reverse: false,
        }}
        yFormat=" >-.2f"
        curve="catmullRom"
        axisTop={null}
        axisRight={null}
        axisBottom={{
          tickSize: 5,
          tickPadding: 5,
          tickRotation: data.length > 6 ? -45 : 0,
          legend: '',
          legendOffset: 36,
          legendPosition: 'middle',
          truncateTickAt: 0,
        }}
        axisLeft={{
          tickSize: 5,
          tickPadding: 5,
          tickRotation: 0,
          legend: measureLabel,
          legendOffset: -50,
          legendPosition: 'middle',
          truncateTickAt: 0,
        }}
        colors={colors}
        lineWidth={3}
        enablePoints={showPoints}
        pointSize={8}
        pointColor={{ theme: 'background' }}
        pointBorderWidth={2}
        pointBorderColor={{ from: 'serieColor' }}
        pointLabel="yFormatted"
        pointLabelYOffset={-12}
        enableArea={enableArea}
        areaOpacity={0.15}
        useMesh={true}
        legends={showLegend ? [
          {
            anchor: 'bottom-right',
            direction: 'column',
            justify: false,
            translateX: 100,
            translateY: 0,
            itemsSpacing: 0,
            itemDirection: 'left-to-right',
            itemWidth: 80,
            itemHeight: 20,
            itemOpacity: 0.75,
            symbolSize: 12,
            symbolShape: 'circle',
            symbolBorderColor: 'rgba(0, 0, 0, .5)',
          }
        ] : []}
        theme={theme}
        animate={true}
        motionConfig="gentle"
      />
    </div>
  );
};

export default LineChartWidget;

