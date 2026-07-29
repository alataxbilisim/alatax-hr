import React, { useMemo } from 'react';
import { useTranslation } from '@shared/i18n';

export interface PivotMatrixProps {
  rowHeaders: { field: string; label: string }[];
  columnHeaders: { field: string; label: string }[];
  rowKeys: string[][];
  columnKeys: string[][];
  measures: { alias: string; format: string; decimals: number }[];
  cells: { row: number; col: number; measure: string; value: string | number | null }[];
  onCellClick?: (rowIndex: number, colIndex: number, measure: string) => void;
}

function formatValue(
  value: string | number | null,
  format: string,
  decimals: number
): string {
  if (value === null || value === undefined) return '—';
  const n = typeof value === 'number' ? value : Number(value);
  if (!Number.isFinite(n)) return String(value);
  if (format === 'percent') {
    return `${(n * 100).toFixed(decimals)}%`;
  }
  if (format === 'money') {
    return n.toLocaleString('tr-TR', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });
  }
  return n.toLocaleString('tr-TR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: decimals,
  });
}

const PivotMatrixTable: React.FC<PivotMatrixProps> = ({
  rowHeaders,
  columnHeaders,
  rowKeys,
  columnKeys,
  measures,
  cells,
  onCellClick,
}) => {
  const { t } = useTranslation('common');

  const lookup = useMemo(() => {
    const map = new Map<string, string | number | null>();
    for (const c of cells) {
      map.set(`${c.row}|${c.col}|${c.measure}`, c.value);
    }
    return map;
  }, [cells]);

  const colSpan = Math.max(columnKeys.length, 1) * Math.max(measures.length, 1);

  return (
    <div
      style={{
        overflow: 'auto',
        maxHeight: 480,
        border: '1px solid var(--border-primary)',
        borderRadius: 'var(--radius-md)',
      }}
    >
      <table className="report-list-table" style={{ minWidth: '100%' }}>
        <thead>
          {columnHeaders.length > 0 ? (
            <tr>
              <th
                style={{ position: 'sticky', left: 0, zIndex: 2, background: 'var(--table-header-bg)' }}
                colSpan={rowHeaders.length || 1}
              />
              {columnKeys.map((ck, ci) => (
                <th key={`ck-${ci}`} colSpan={measures.length}>
                  {ck.join(' / ')}
                </th>
              ))}
            </tr>
          ) : null}
          <tr>
            {(rowHeaders.length ? rowHeaders : [{ field: '_', label: t('reportEngine.pivot.rows') }]).map(
              (h) => (
                <th
                  key={h.field}
                  style={{ position: 'sticky', left: 0, zIndex: 2, background: 'var(--table-header-bg)' }}
                >
                  {h.label}
                </th>
              )
            )}
            {(columnKeys.length ? columnKeys : [['']]).flatMap((_, ci) =>
              measures.map((m) => (
                <th key={`m-${ci}-${m.alias}`}>{m.alias}</th>
              ))
            )}
          </tr>
        </thead>
        <tbody>
          {rowKeys.length === 0 ? (
            <tr>
              <td colSpan={(rowHeaders.length || 1) + colSpan}>
                {t('reportEngine.previewEmpty')}
              </td>
            </tr>
          ) : (
            rowKeys.map((rk, ri) => (
              <tr key={`r-${ri}`}>
                {rk.map((label, i) => (
                  <td
                    key={`rk-${ri}-${i}`}
                    style={{
                      position: 'sticky',
                      left: i * 100,
                      background: 'var(--bg-elevated)',
                      fontWeight: 500,
                    }}
                  >
                    {label}
                  </td>
                ))}
                {(columnKeys.length ? columnKeys : [[]]).flatMap((_, ci) =>
                  measures.map((m) => {
                    const raw = lookup.get(`${ri}|${ci}|${m.alias}`) ?? null;
                    return (
                      <td
                        key={`c-${ri}-${ci}-${m.alias}`}
                        style={{
                          cursor: onCellClick ? 'pointer' : 'default',
                          textAlign: 'right',
                        }}
                        onClick={() => onCellClick?.(ri, ci, m.alias)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') onCellClick?.(ri, ci, m.alias);
                        }}
                        role={onCellClick ? 'button' : undefined}
                        tabIndex={onCellClick ? 0 : undefined}
                      >
                        {formatValue(raw, m.format, m.decimals)}
                      </td>
                    );
                  })
                )}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
};

export default PivotMatrixTable;
