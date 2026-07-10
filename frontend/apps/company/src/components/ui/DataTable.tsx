import React from 'react';
import { BsChevronLeft, BsChevronRight, BsSearch } from 'react-icons/bs';

interface Column<T> {
  key: string;
  title: string;
  render?: (item: T, index: number) => React.ReactNode;
  width?: string;
  align?: 'left' | 'center' | 'right';
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  loading?: boolean;
  emptyMessage?: string;
  emptyIcon?: React.ReactNode;
  // Pagination
  currentPage?: number;
  totalPages?: number;
  total?: number;
  onPageChange?: (page: number) => void;
  // Search
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  searchPlaceholder?: string;
  // Actions
  headerActions?: React.ReactNode;
}

function DataTable<T extends { id?: number | string }>({
  columns,
  data,
  loading = false,
  emptyMessage = 'Kayıt bulunamadı',
  emptyIcon,
  currentPage = 1,
  totalPages = 1,
  total,
  onPageChange,
  searchValue,
  onSearchChange,
  searchPlaceholder = 'Ara...',
  headerActions,
}: DataTableProps<T>) {
  return (
    <div className="card">
      {/* Header with search and actions */}
      {(onSearchChange || headerActions) && (
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

      {/* Table */}
      <div className="table-container">
        <table className="table">
          <thead>
            <tr>
              {columns.map((col) => (
                <th
                  key={col.key}
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
              // Loading skeleton
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
              // Empty state
              <tr>
                <td colSpan={columns.length}>
                  <div className="empty-state" style={{ padding: '2rem' }}>
                    {emptyIcon && <div className="empty-state-icon">{emptyIcon}</div>}
                    <p style={{ color: 'var(--text-tertiary)', margin: 0 }}>{emptyMessage}</p>
                  </div>
                </td>
              </tr>
            ) : (
              // Data rows
              data.map((item, rowIdx) => (
                <tr key={item.id || rowIdx}>
                  {columns.map((col) => (
                    <td
                      key={col.key}
                      style={{ textAlign: col.align || 'left' }}
                    >
                      {col.render
                        ? col.render(item, rowIdx)
                        : (item as Record<string, unknown>)[col.key] as React.ReactNode}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {totalPages > 1 && onPageChange && (
        <div className="card-footer d-flex justify-content-between align-items-center">
          <span style={{ fontSize: '0.8125rem', color: 'var(--text-tertiary)' }}>
            {total !== undefined ? `Toplam ${total} kayıt` : `Sayfa ${currentPage} / ${totalPages}`}
          </span>
          <div className="d-flex gap-1">
            <button
              className="btn btn-ghost btn-sm"
              disabled={currentPage <= 1}
              onClick={() => onPageChange(currentPage - 1)}
            >
              <BsChevronLeft />
            </button>
            
            {/* Page numbers */}
            {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
              let pageNum: number;
              if (totalPages <= 5) {
                pageNum = i + 1;
              } else if (currentPage <= 3) {
                pageNum = i + 1;
              } else if (currentPage >= totalPages - 2) {
                pageNum = totalPages - 4 + i;
              } else {
                pageNum = currentPage - 2 + i;
              }
              
              return (
                <button
                  key={pageNum}
                  className={`btn btn-sm ${currentPage === pageNum ? 'btn-primary' : 'btn-ghost'}`}
                  onClick={() => onPageChange(pageNum)}
                  style={{ minWidth: 32 }}
                >
                  {pageNum}
                </button>
              );
            })}
            
            <button
              className="btn btn-ghost btn-sm"
              disabled={currentPage >= totalPages}
              onClick={() => onPageChange(currentPage + 1)}
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

