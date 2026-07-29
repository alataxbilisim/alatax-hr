import React, { useMemo, useRef, useState } from 'react';
import {
  useReactTable,
  getCoreRowModel,
  flexRender,
  type ColumnDef,
  type SortingState,
  type ColumnSizingState,
} from '@tanstack/react-table';
import { useVirtualizer } from '@tanstack/react-virtual';
import type { ReportRow, ReportTableColumn } from './types';

export interface ReportTableProps {
  columns: ReportTableColumn[];
  rows: ReportRow[];
  /** Sunucu tarafı sıralama değişince */
  onSortChange?: (sorts: { field: string; dir: 'asc' | 'desc' }[]) => void;
  sorting?: SortingState;
  height?: number;
  emptyLabel?: string;
  className?: string;
  /** Sunucu sayfalama bilgisi (görüntü) */
  pageInfo?: { offset: number; limit: number; count: number };
  onPageChange?: (offset: number) => void;
}

export const ReportTable: React.FC<ReportTableProps> = ({
  columns,
  rows,
  onSortChange,
  sorting: controlledSorting,
  height = 420,
  emptyLabel,
  className,
  pageInfo,
  onPageChange,
}) => {
  const [internalSorting, setInternalSorting] = useState<SortingState>([]);
  const [columnSizing, setColumnSizing] = useState<ColumnSizingState>({});
  const sorting = controlledSorting ?? internalSorting;
  const parentRef = useRef<HTMLDivElement>(null);

  const columnDefs = useMemo<ColumnDef<ReportRow>[]>(
    () =>
      columns.map((col) => ({
        id: col.id,
        accessorKey: col.accessorKey,
        header: col.header,
        size: col.size ?? 140,
        enableResizing: true,
        cell: (info) => {
          const v = info.getValue();
          if (v === null || v === undefined) return '—';
          return String(v);
        },
      })),
    [columns]
  );

  const table = useReactTable({
    data: rows,
    columns: columnDefs,
    state: { sorting, columnSizing },
    onSortingChange: (updater) => {
      const next = typeof updater === 'function' ? updater(sorting) : updater;
      if (!controlledSorting) setInternalSorting(next);
      onSortChange?.(
        next.map((s) => ({
          field: s.id,
          dir: s.desc ? 'desc' : 'asc',
        }))
      );
    },
    onColumnSizingChange: setColumnSizing,
    getCoreRowModel: getCoreRowModel(),
    manualSorting: true,
    columnResizeMode: 'onChange',
    enableColumnResizing: true,
  });

  const { rows: tableRows } = table.getRowModel();
  const rowVirtualizer = useVirtualizer({
    count: tableRows.length,
    getScrollElement: () => parentRef.current,
    estimateSize: () => 42,
    overscan: 12,
  });

  const canPrev = pageInfo ? pageInfo.offset > 0 : false;
  const canNext = pageInfo
    ? pageInfo.count >= pageInfo.limit
    : false;

  if (columns.length === 0) {
    return (
      <div className={className} style={{ color: 'var(--text-tertiary)', padding: 'var(--sp-4)' }}>
        {emptyLabel ?? '—'}
      </div>
    );
  }

  return (
    <div className={className}>
      <div
        ref={parentRef}
        style={{
          height,
          overflow: 'auto',
          border: '1px solid var(--border-primary)',
          borderRadius: 'var(--radius-md)',
          background: 'var(--bg-elevated)',
        }}
      >
        <table style={{ width: table.getTotalSize(), borderCollapse: 'collapse' }}>
          <thead
            style={{
              position: 'sticky',
              top: 0,
              zIndex: 1,
              background: 'var(--table-header-bg)',
            }}
          >
            {table.getHeaderGroups().map((hg) => (
              <tr key={hg.id} style={{ height: 'var(--table-header-height)' }}>
                {hg.headers.map((header) => (
                  <th
                    key={header.id}
                    style={{
                      width: header.getSize(),
                      position: 'relative',
                      textAlign: 'left',
                      padding: `0 var(--table-cell-padding-x)`,
                      fontSize: 'var(--fs-label)',
                      fontWeight: 500,
                      color: 'var(--text-secondary)',
                      borderBottom: '1px solid var(--table-border)',
                      cursor: header.column.getCanSort() ? 'pointer' : 'default',
                      userSelect: 'none',
                    }}
                    onClick={header.column.getToggleSortingHandler()}
                  >
                    {flexRender(header.column.columnDef.header, header.getContext())}
                    {header.column.getIsSorted() === 'asc'
                      ? ' ↑'
                      : header.column.getIsSorted() === 'desc'
                        ? ' ↓'
                        : null}
                    <div
                      onMouseDown={header.getResizeHandler()}
                      onTouchStart={header.getResizeHandler()}
                      style={{
                        position: 'absolute',
                        right: 0,
                        top: 0,
                        height: '100%',
                        width: 4,
                        cursor: 'col-resize',
                        userSelect: 'none',
                      }}
                    />
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody style={{ position: 'relative', height: `${rowVirtualizer.getTotalSize()}px` }}>
            {tableRows.length === 0 ? (
              <tr>
                <td
                  colSpan={columns.length}
                  style={{
                    padding: 'var(--sp-6)',
                    textAlign: 'center',
                    color: 'var(--text-tertiary)',
                    fontSize: 'var(--fs-body)',
                  }}
                >
                  {emptyLabel ?? '—'}
                </td>
              </tr>
            ) : (
              rowVirtualizer.getVirtualItems().map((virtualRow) => {
                const row = tableRows[virtualRow.index];
                if (!row) return null;
                return (
                  <tr
                    key={row.id}
                    style={{
                      position: 'absolute',
                      top: 0,
                      left: 0,
                      width: '100%',
                      height: `${virtualRow.size}px`,
                      transform: `translateY(${virtualRow.start}px)`,
                      display: 'table',
                      tableLayout: 'fixed',
                    }}
                  >
                    {row.getVisibleCells().map((cell) => (
                      <td
                        key={cell.id}
                        style={{
                          width: cell.column.getSize(),
                          padding: `0 var(--table-cell-padding-x)`,
                          fontSize: 'var(--fs-table)',
                          color: 'var(--text-primary)',
                          borderBottom: '1px solid var(--table-border)',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap',
                        }}
                      >
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </td>
                    ))}
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
      {pageInfo && onPageChange ? (
        <div
          style={{
            display: 'flex',
            justifyContent: 'flex-end',
            gap: 'var(--sp-2)',
            marginTop: 'var(--sp-2)',
            fontSize: 'var(--fs-caption)',
            color: 'var(--text-secondary)',
          }}
        >
          <span>
            {pageInfo.offset + 1}–{pageInfo.offset + pageInfo.count}
          </span>
          <button
            type="button"
            className="btn btn-sm btn-ghost"
            disabled={!canPrev}
            onClick={() => onPageChange(Math.max(0, pageInfo.offset - pageInfo.limit))}
          >
            ‹
          </button>
          <button
            type="button"
            className="btn btn-sm btn-ghost"
            disabled={!canNext}
            onClick={() => onPageChange(pageInfo.offset + pageInfo.limit)}
          >
            ›
          </button>
        </div>
      ) : null}
    </div>
  );
};
