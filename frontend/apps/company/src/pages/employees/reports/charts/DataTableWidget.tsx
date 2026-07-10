import React from 'react';
import type { ChartDataItem } from '../types';

interface DataTableWidgetProps {
  data: ChartDataItem[];
  dimensionLabel?: string;
  measureLabel?: string;
}

const DataTableWidget: React.FC<DataTableWidgetProps> = ({
  data,
  dimensionLabel = 'Kategori',
  measureLabel = 'Değer',
}) => {
  const total = data.reduce((sum, item) => sum + item.value, 0);

  if (data.length === 0) {
    return (
      <div className="chart-empty-state">
        <p>Veri bulunamadı</p>
      </div>
    );
  }

  return (
    <div className="report-data-table-container">
      <table className="report-data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{dimensionLabel}</th>
            <th style={{ textAlign: 'right' }}>{measureLabel}</th>
            <th style={{ textAlign: 'right' }}>Oran</th>
            <th style={{ width: '30%' }}>Dağılım</th>
          </tr>
        </thead>
        <tbody>
          {data.map((item, index) => {
            const percentage = total > 0 ? ((item.value / total) * 100).toFixed(1) : '0';
            return (
              <tr key={item.id}>
                <td className="text-muted">{index + 1}</td>
                <td>
                  <span className="report-table-label">{item.label}</span>
                </td>
                <td style={{ textAlign: 'right' }}>
                  <strong>{item.value.toLocaleString('tr-TR')}</strong>
                </td>
                <td style={{ textAlign: 'right' }}>
                  <span className="text-muted">%{percentage}</span>
                </td>
                <td>
                  <div className="report-table-bar">
                    <div 
                      className="report-table-bar-fill"
                      style={{ 
                        width: `${total > 0 ? (item.value / total) * 100 : 0}%`,
                      }}
                    />
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
        <tfoot>
          <tr>
            <td colSpan={2}><strong>Toplam</strong></td>
            <td style={{ textAlign: 'right' }}><strong>{total.toLocaleString('tr-TR')}</strong></td>
            <td style={{ textAlign: 'right' }}><strong>%100</strong></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  );
};

export default DataTableWidget;

