import React from 'react';
import { ResponsiveTreeMap } from '@nivo/treemap';
import type { ColorSchemeId } from '@nivo/colors';
import type { ChartDataItem } from '../types';

interface TreemapWidgetProps {
  data: ChartDataItem[];
  colorScheme?: ColorSchemeId;
}

const TreemapWidget: React.FC<TreemapWidgetProps> = ({
  data,
  colorScheme = 'nivo',
}) => {
  if (!data || data.length === 0) {
    return (
      <div className="widget-no-data">
        <span>Veri bulunamadı</span>
      </div>
    );
  }

  const treemapData = {
    name: 'root',
    children: data.map((item) => ({
      name: item.label,
      value: item.value,
    })),
  };

  return (
    <div className="treemap-widget" style={{ height: '100%', minHeight: 200 }}>
      <ResponsiveTreeMap
        data={treemapData}
        identity="name"
        value="value"
        valueFormat=".0f"
        margin={{ top: 10, right: 10, bottom: 10, left: 10 }}
        labelSkipSize={24}
        labelTextColor={{ from: 'color', modifiers: [['darker', 2.5]] }}
        parentLabelPosition="left"
        parentLabelTextColor={{ from: 'color', modifiers: [['darker', 3]] }}
        colors={{ scheme: colorScheme }}
        borderColor={{ from: 'color', modifiers: [['darker', 0.3]] }}
        animate={true}
        motionConfig="gentle"
        tile="squarify"
        innerPadding={3}
        outerPadding={3}
        leavesOnly={true}
        theme={{
          labels: {
            text: {
              fontSize: 12,
              fontWeight: 600,
            },
          },
          tooltip: {
            container: {
              background: '#1a1d23',
              color: '#e5e7eb',
              borderRadius: '8px',
              boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
            },
          },
        }}
        tooltip={({ node }) => (
          <div
            style={{
              padding: '8px 12px',
              background: '#1a1d23',
              borderRadius: '6px',
              color: '#e5e7eb',
            }}
          >
            <strong>{node.id}</strong>
            <br />
            <span>Değer: {node.value.toLocaleString('tr-TR')}</span>
          </div>
        )}
      />
    </div>
  );
};

export default TreemapWidget;
