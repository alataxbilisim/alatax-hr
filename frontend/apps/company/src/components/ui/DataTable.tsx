import React, { useMemo } from 'react';
import { BsChevronLeft, BsChevronRight, BsSearch } from 'react-icons/bs';

export interface Column<T> {
  key: string;
  title: React.ReactNode;
  render?: (item: T, index: number) => React.ReactNode;
  width?: string;
  align?: 'left' | 'center' | 'right';
  className?: string;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  loading?: boolean;
  emptyMessage?: string;
  emptyIcon?: React.ReactNode;
  emptyAction?: React.ReactNode;
  // Pagination
  currentPage?: number;
  totalPages?: number;
  total?: number;
  onPageChange?: (page: number) => void;
  // Search (opsiyonel — liste sayfalarında filtre şeridi dışarıda olabilir)
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  searchPlaceholder?: string;
  // Actions
  headerActions?: React.ReactNode;
  /** Satır seçili görünümü */
  isRowSelected?: (item: T) => boolean;
  getRowKey?: (item: T, index: number) => string | number;
  className?: string;
}

function DataTable<T extends { id?: number | string }>({
  columns,
  data,
  loading = false,
  emptyMessage = 'Kayıt bulunamadı',
  emptyIcon,
  emptyAction,
  currentPage = 1,
  totalPages = 1,
  total,
  onPageChange,
  searchValue,
  onSearchChange,
  searchPlaceholder = 'Ara...',
  headerActions,
  isRowSelected,
  getRowKey,
  className,
}: DataTableProps<T>) {
  const showHeader = Boolean(onSearchChange || headerActions);

  const pageButtons = useMemo(() => {
    return Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
      if (totalPages <= 5) return i + 1;
      if (currentPage <= 3) return i + 1;
      if (currentPage >= totalPages - 2) return totalPages - 4 + i;
      return currentPage - 2 + i;
    });
  }, [currentPage, totalPages]);

  return (
    <div className={`card data-table${className ? ` ${className}` : ''}`}>
      {showHeader && (
        <div className="card-header">
          {onSearchChange && (
            <div className="input-group" style={{ maxWidth: 280 }}>
              <span className="input-icon"><BsSearch /></span>
              <input
                type="text"
                className="form-control"
                placeholder={searchPlaceholder}
                value={searchValue || ''}
                onChange={(e) => onSearchChange(e.target.value)}
                style={{ paddingLeft: '2.25rem' }}
              />
            </div>
          )}
          {headerActions && (
            <div className="d-flex gap-2">
              {headerActions}
            </div>
          )}
        </div>
      )}

      <div className="table-container">
        <table className="table">
          <thead>
            <tr>
              {columns.map((col) => (
                <th
                  key={col.key}
                  className={col.className}
                  style={{
                    width: col.width,
                    textAlign: col.align || 'left',
                  }}
                >
                  {col.title}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {loading ? (
              Array.from({ length: 5 }).map((_, idx) => (
                <tr key={idx}>
                  {columns.map((col) => (
                    <td key={col.key}>
                      <div className="skeleton skeleton-text" style={{ width: '80%' }} />
                    </td>
                  ))}
                </tr>
              ))
            ) : data.length === 0 ? (
              <tr>
                <td colSpan={columns.length}>
                  <div className="empty-state" style={{ padding: 'var(--sp-6)' }}>
                    {emptyIcon && <div className="empty-state-icon">{emptyIcon}</div>}
                    <p style={{ color: 'var(--text-tertiary)', margin: 0, fontSize: 'var(--fs-body)' }}>
                      {emptyMessage}
                    </p>
                    {emptyAction && <div style={{ marginTop: 'var(--sp-3)' }}>{emptyAction}</div>}
                  </div>
                </td>
              </tr>
            ) : (
              data.map((item, rowIdx) => {
                const selected = isRowSelected?.(item) ?? false;
                return (
                  <tr
                    key={getRowKey ? getRowKey(item, rowIdx) : (item.id ?? rowIdx)}
                    style={selected ? { background: 'var(--primary-soft)' } : undefined}
                  >
                    {columns.map((col) => (
                      <td
                        key={col.key}
                        className={col.className}
                        style={{ textAlign: col.align || 'left' }}
                      >
                        {col.render
                          ? col.render(item, rowIdx)
                          : (item as Record<string, unknown>)[col.key] as React.ReactNode}
                      </td>
                    ))}
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {totalPages > 1 && onPageChange && (
        <div className="card-footer d-flex justify-content-between align-items-center">
          <span style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)' }}>
            {total !== undefined ? `Toplam ${total} kayıt` : `Sayfa ${currentPage} / ${totalPages}`}
          </span>
          <div className="d-flex gap-1">
            <button
              type="button"
              className="btn btn-ghost btn-sm"
              disabled={currentPage <= 1}
              onClick={() => onPageChange(currentPage - 1)}
              aria-label="Önceki sayfa"
            >
              <BsChevronLeft />
            </button>

            {pageButtons.map((pageNum) => (
              <button
                type="button"
                key={pageNum}
                className={`btn btn-sm ${currentPage === pageNum ? 'btn-primary' : 'btn-ghost'}`}
                onClick={() => onPageChange(pageNum)}
                style={{ minWidth: 'var(--btn-height-sm)' }}
              >
                {pageNum}
              </button>
            ))}

            <button
              type="button"
              className="btn btn-ghost btn-sm"
              disabled={currentPage >= totalPages}
              onClick={() => onPageChange(currentPage + 1)}
              aria-label="Sonraki sayfa"
            >
              <BsChevronRight />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default DataTable;
