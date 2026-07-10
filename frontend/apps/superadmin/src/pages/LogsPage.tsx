import React, { useEffect, useState, useCallback } from 'react';
import { adminApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsJournalText } from 'react-icons/bs';

interface LogEntry {
  id: number;
  log_name: string;
  description: string;
  subject_type: string | null;
  subject_id: number | null;
  causer_type: string | null;
  causer_id: number | null;
  causer?: {
    id: number;
    name: string;
    email: string;
  };
  properties: Record<string, unknown>;
  created_at: string;
}

const LogsPage: React.FC = () => {
  const [logs, setLogs] = useState<{ data: LogEntry[]; total: number; last_page: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);

  const loadLogs = useCallback(async () => {
    try {
      setLoading(true);
      const response = await adminApi.logs({ page, per_page: 30 });
      setLogs(response.data.data);
    } catch {
      toast.error('Loglar yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    loadLogs();
  }, [loadLogs]);

  const getLogColor = (logName: string) => {
    const colors: Record<string, string> = {
      created: 'success',
      updated: 'warning',
      deleted: 'danger',
      login: 'primary',
      logout: 'secondary',
    };
    return colors[logName] || 'default';
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Sistem Logları</h1>
          <p className="page-subtitle">Tüm sistem aktivitelerini izleyin</p>
        </div>
      </div>

      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : logs?.data && logs.data.length > 0 ? (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Tarih</th>
                    <th>İşlem</th>
                    <th>Açıklama</th>
                    <th>Kullanıcı</th>
                    <th>Kaynak</th>
                  </tr>
                </thead>
                <tbody>
                  {logs.data.map((log) => (
                    <tr key={log.id}>
                      <td className="text-nowrap">
                        {new Date(log.created_at).toLocaleString('tr-TR')}
                      </td>
                      <td>
                        <span className={`status-badge ${getLogColor(log.log_name)}`}>
                          {log.log_name}
                        </span>
                      </td>
                      <td>{log.description}</td>
                      <td>
                        {log.causer ? (
                          <div>
                            <div className="fw-semibold">{log.causer.name}</div>
                            <div className="text-muted small">{log.causer.email}</div>
                          </div>
                        ) : (
                          <span className="text-muted">Sistem</span>
                        )}
                      </td>
                      <td>
                        {log.subject_type ? (
                          <span className="small text-muted">
                            {log.subject_type.split('\\').pop()} #{log.subject_id}
                          </span>
                        ) : (
                          '-'
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="empty-state">
              <BsJournalText size={48} />
              <h3>Log kaydı bulunamadı</h3>
            </div>
          )}
        </div>

        {/* Pagination */}
        {logs && logs.last_page > 1 && (
          <div className="card-footer d-flex justify-content-between align-items-center">
            <span className="text-muted">
              Toplam {logs.total} kayıt
            </span>
            <div className="btn-group">
              <button
                className="btn btn-sm btn-secondary"
                disabled={page === 1}
                onClick={() => setPage(page - 1)}
              >
                Önceki
              </button>
              <span className="btn btn-sm btn-ghost disabled">
                {page} / {logs.last_page}
              </span>
              <button
                className="btn btn-sm btn-secondary"
                disabled={page === logs.last_page}
                onClick={() => setPage(page + 1)}
              >
                Sonraki
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default LogsPage;

