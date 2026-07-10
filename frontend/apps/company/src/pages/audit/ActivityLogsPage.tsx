import React, { useEffect, useState, useCallback } from 'react';
import { activityLogsApi, usersApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { DataTable, EmptyState, Modal, Skeleton } from '../../components/ui';
import {
  BsJournalText,
  BsSearch,
  BsFilter,
  BsX,
  BsEye,
  BsCheckCircle,
  BsXCircle,
  BsDownload,
} from 'react-icons/bs';

interface ActivityLog {
  id: number;
  user: { id: number; name: string; email: string };
  action: string;
  model_type: string;
  model_id: number;
  description?: string;
  ip_address?: string;
  user_agent?: string;
  old_values?: Record<string, unknown>;
  new_values?: Record<string, unknown>;
  url?: string;
  method?: string;
  is_successful?: boolean;
  error_message?: string;
  created_at: string;
}

interface User {
  id: number;
  name: string;
  email: string;
}

const actionLabels: Record<string, string> = {
  // CRUD işlemleri
  create: 'Oluşturuldu',
  created: 'Oluşturuldu',
  update: 'Güncellendi',
  updated: 'Güncellendi',
  delete: 'Silindi',
  deleted: 'Silindi',
  viewed: 'Görüntülendi',
  export: 'Dışa Aktarıldı',
  // Auth işlemleri
  login: 'Giriş Yapıldı',
  logout: 'Çıkış Yapıldı',
  register: 'Kayıt Olundu',
  password_change: 'Şifre Değiştirildi',
  // Onay işlemleri
  approved: 'Onaylandı',
  rejected: 'Reddedildi',
  // Leave işlemleri
  leave_requested: 'İzin Talep Edildi',
  leave_approved: 'İzin Onaylandı',
  leave_rejected: 'İzin Reddedildi',
  leave_cancelled: 'İzin İptal Edildi',
  // Attendance işlemleri
  clock_in: 'Giriş Yapıldı',
  clock_out: 'Çıkış Yapıldı',
  break_started: 'Mola Başladı',
  break_ended: 'Mola Bitti',
  attendance_created: 'Devam Kaydı Oluşturuldu',
  attendance_updated: 'Devam Kaydı Güncellendi',
  attendance_approved: 'Devam Kaydı Onaylandı',
  attendance_bulk_approved: 'Toplu Onay Yapıldı',
  // Expense işlemleri
  expense_claim_created: 'Masraf Talebi Oluşturuldu',
  expense_claim_updated: 'Masraf Talebi Güncellendi',
  expense_claim_submitted: 'Masraf Talebi Gönderildi',
  expense_claim_cancelled: 'Masraf Talebi İptal Edildi',
  expense_receipt_uploaded: 'Masraf Fişi Yüklendi',
  // Performance işlemleri
  goal_created: 'Hedef Oluşturuldu',
  goal_completed: 'Hedef Tamamlandı',
  review_submitted: 'Değerlendirme Gönderildi',
  review_completed: 'Değerlendirme Tamamlandı',
};

const ActivityLogsPage: React.FC = () => {
  const [logs, setLogs] = useState<ActivityLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);

  // Filters
  const [search, setSearch] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [userId, setUserId] = useState('');
  const [action, setAction] = useState('');
  const [showFilters, setShowFilters] = useState(false);
  const [users, setUsers] = useState<User[]>([]);
  
  // Detail modal
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedLog, setSelectedLog] = useState<ActivityLog | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const loadUsers = useCallback(async () => {
    try {
      const response = await usersApi.list({ per_page: 100 });
      setUsers(response.data.data?.data || response.data.data || []);
    } catch {
      // Silent fail
    }
  }, []);

  const loadLogs = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = { page };
      if (search) params.search = search;
      if (dateFrom) params.date_from = dateFrom;
      if (dateTo) params.date_to = dateTo;
      if (userId) params.user_id = userId;
      if (action) params.action = action;

      const response = await activityLogsApi.list(params);
      const responseData = response.data;
      const meta = responseData.meta;
      
      // Backend doğrudan array döndürüyor, meta bilgisi ayrı
      if (Array.isArray(responseData.data)) {
        setLogs(responseData.data);
        setTotalPages(meta?.last_page || 1);
        setTotal(meta?.total || responseData.data.length);
      } else if (responseData.data && typeof responseData.data === 'object') {
        // Eski yapı: data.data içinde array varsa
        const logs = responseData.data.data || [];
        setLogs(logs);
        setTotalPages(responseData.data.last_page || 1);
        setTotal(responseData.data.total || 0);
      } else {
        setLogs([]);
        setTotalPages(1);
        setTotal(0);
      }
    } catch (error: unknown) {
      console.error('Activity logs error:', error);
      toast.error(getErrorMessage(error, 'Loglar yüklenemedi'));
      setLogs([]);
    } finally {
      setLoading(false);
    }
  }, [page, search, dateFrom, dateTo, userId, action]);

  useEffect(() => {
    loadLogs();
    loadUsers();
  }, [loadUsers, loadLogs]);

  

  

  const clearFilters = () => {
    setSearch('');
    setDateFrom('');
    setDateTo('');
    setUserId('');
    setAction('');
  };

  const formatDate = (date: string) => {
    return new Date(date).toLocaleString('tr-TR');
  };

  const getModelLabel = (modelType: string) => {
    const labels: Record<string, string> = {
      User: 'Kullanıcı',
      Role: 'Rol',
      Company: 'Firma',
      LeaveRequest: 'İzin Talebi',
      LeaveType: 'İzin Türü',
      Document: 'Evrak',
      JobPosition: 'İş Pozisyonu',
      Application: 'Başvuru',
      OnboardingProcess: 'Onboarding Süreci',
      OnboardingTemplate: 'Onboarding Şablonu',
      PerformanceReview: 'Performans Değerlendirmesi',
      PerformancePeriod: 'Performans Dönemi',
      Training: 'Eğitim',
      TrainingSession: 'Eğitim Oturumu',
      Asset: 'Varlık',
    };
    return labels[modelType] || modelType;
  };

  const columns = [
    {
      key: 'created_at',
      title: 'Tarih',
      render: (log: ActivityLog) => (
        <div style={{ fontSize: '0.875rem' }}>
          {formatDate(log.created_at)}
        </div>
      ),
    },
    {
      key: 'user',
      title: 'Kullanıcı',
      render: (log: ActivityLog) => (
        <div>
          <div style={{ fontWeight: 500 }}>{log.user?.name || 'Sistem'}</div>
          {log.user?.email && (
            <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
              {log.user.email}
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'action',
      title: 'İşlem',
      render: (log: ActivityLog) => {
        const getActionBadgeClass = (action: string) => {
          if (action.includes('create') || action.includes('approved') || action === 'login' || action === 'clock_in') return 'badge-success';
          if (action.includes('update') || action === 'clock_out' || action.includes('break')) return 'badge-info';
          if (action.includes('delete') || action.includes('rejected') || action.includes('cancelled')) return 'badge-danger';
          if (action === 'export' || action === 'logout') return 'badge-warning';
          return 'badge-secondary';
        };
        return (
          <span className={`badge ${getActionBadgeClass(log.action)}`}>
            {actionLabels[log.action] || log.action}
          </span>
        );
      },
    },
    {
      key: 'model',
      title: 'Model',
      render: (log: ActivityLog) => (
        <div>
          <div style={{ fontWeight: 500 }}>{getModelLabel(log.model_type)}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            ID: {log.model_id}
          </div>
        </div>
      ),
    },
    {
      key: 'description',
      title: 'Açıklama',
      render: (log: ActivityLog) => (
        <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
          {log.description || '-'}
        </div>
      ),
    },
    {
      key: 'ip_address',
      title: 'IP Adresi',
      render: (log: ActivityLog) => log.ip_address || '-',
    },
    {
      key: 'actions',
      title: 'İşlemler',
      width: '80px',
      align: 'right' as const,
      render: (log: ActivityLog) => (
        <button
          className="btn btn-ghost btn-icon btn-sm"
          onClick={() => handleViewDetail(log)}
          title="Detayları Görüntüle"
        >
          <BsEye />
        </button>
      ),
    },
  ];

  const handleViewDetail = async (log: ActivityLog) => {
    setDetailLoading(true);
    setDetailModalOpen(true);
    try {
      const response = await activityLogsApi.get(log.id);
      setSelectedLog(response.data.data);
    } catch {
      toast.error('Log detayı yüklenemedi');
      setDetailModalOpen(false);
    } finally {
      setDetailLoading(false);
    }
  };

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div>
          <h1 className="page-title">Log & Denetim</h1>
          <p className="page-subtitle">Sistem ve kullanıcı işlem logları</p>
        </div>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          <button
            className="btn btn-ghost"
            onClick={async () => {
              try {
                const params: Record<string, unknown> = {};
                if (dateFrom) params.date_from = dateFrom;
                if (dateTo) params.date_to = dateTo;
                if (userId) params.user_id = userId;
                if (action) params.action = action;

                const response = await activityLogsApi.export(params);
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', `activity_logs_${new Date().toISOString().split('T')[0]}.csv`);
                document.body.appendChild(link);
                link.click();
                link.remove();
                toast.success('Loglar export edildi');
              } catch {
                toast.error('Export başarısız');
              }
            }}
          >
            <BsDownload size={18} />
            Export
          </button>
          <button
            className="btn btn-ghost"
            onClick={() => setShowFilters(!showFilters)}
          >
            <BsFilter size={18} />
            Filtreler
          </button>
        </div>
      </div>

      {/* Filters */}
      {showFilters && (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-body">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              <div className="form-group">
                <label className="form-label">Arama</label>
                <div style={{ position: 'relative' }}>
                  <BsSearch size={16} style={{ position: 'absolute', left: '0.75rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-tertiary)' }} />
                  <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="form-input"
                    placeholder="Açıklama, model..."
                    style={{ paddingLeft: '2.5rem' }}
                  />
                </div>
              </div>

              <div className="form-group">
                <label className="form-label">Başlangıç Tarihi</label>
                <input
                  type="date"
                  value={dateFrom}
                  onChange={(e) => setDateFrom(e.target.value)}
                  className="form-input"
                />
              </div>

              <div className="form-group">
                <label className="form-label">Bitiş Tarihi</label>
                <input
                  type="date"
                  value={dateTo}
                  onChange={(e) => setDateTo(e.target.value)}
                  className="form-input"
                />
              </div>

              <div className="form-group">
                <label className="form-label">Kullanıcı</label>
                <select
                  value={userId}
                  onChange={(e) => setUserId(e.target.value)}
                  className="form-input"
                >
                  <option value="">Tümü</option>
                  {users.map(u => (
                    <option key={u.id} value={u.id}>{u.name}</option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">İşlem</label>
                <select
                  value={action}
                  onChange={(e) => setAction(e.target.value)}
                  className="form-input"
                >
                  <option value="">Tümü</option>
                  {Object.entries(actionLabels).map(([value, label]) => (
                    <option key={value} value={value}>{label}</option>
                  ))}
                </select>
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1rem' }}>
              <button className="btn btn-ghost btn-sm" onClick={clearFilters}>
                <BsX size={16} />
                Temizle
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Content */}
      {loading ? (
        <div className="card">
          <div className="card-body">
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              {[...Array(5)].map((_, i) => (
                <div key={i} style={{ display: 'grid', gridTemplateColumns: '150px 1fr 100px 150px 1fr 120px 80px', gap: '1rem', alignItems: 'center' }}>
                  <Skeleton height={16} />
                  <Skeleton height={16} />
                  <Skeleton height={24} />
                  <Skeleton height={16} />
                  <Skeleton height={16} />
                  <Skeleton height={16} />
                  <Skeleton height={24} />
                </div>
              ))}
            </div>
          </div>
        </div>
      ) : logs.length === 0 ? (
        <EmptyState
          icon={<BsJournalText size={48} />}
          title="Henüz log kaydı yok"
          description="Sistem aktiviteleri burada görüntülenecektir."
        />
      ) : (
        <>
          <div style={{ marginBottom: '1rem', fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
            Toplam {total} kayıt bulundu
          </div>
          <DataTable
            columns={columns}
            data={logs}
            currentPage={page}
            totalPages={totalPages}
            onPageChange={setPage}
          />
        </>
      )}

      {/* Log Detail Modal */}
      <Modal
        isOpen={detailModalOpen}
        onClose={() => {
          setDetailModalOpen(false);
          setSelectedLog(null);
        }}
        title="Log Detayı"
        size="xl"
      >
        {detailLoading ? (
          <div className="page-loading">
            <div className="loading-spinner" />
          </div>
        ) : selectedLog ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
            {/* Genel Bilgiler */}
            <div>
              <h4 style={{ marginBottom: '1rem', fontSize: '1rem', fontWeight: 600 }}>Genel Bilgiler</h4>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '1rem' }}>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>Tarih</div>
                  <div style={{ fontWeight: 500 }}>{formatDate(selectedLog.created_at)}</div>
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>Kullanıcı</div>
                  <div style={{ fontWeight: 500 }}>{selectedLog.user?.name || 'Sistem'}</div>
                  {selectedLog.user?.email && (
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{selectedLog.user.email}</div>
                  )}
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>İşlem</div>
                  <span className={`badge ${
                    selectedLog.action.includes('create') || selectedLog.action.includes('approved') ? 'badge-success' :
                    selectedLog.action.includes('update') ? 'badge-info' :
                    selectedLog.action.includes('delete') || selectedLog.action.includes('rejected') ? 'badge-danger' :
                    'badge-secondary'
                  }`}>
                    {actionLabels[selectedLog.action] || selectedLog.action}
                  </span>
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>Durum</div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    {selectedLog.is_successful !== false ? (
                      <>
                        <BsCheckCircle style={{ color: 'var(--success)' }} />
                        <span style={{ color: 'var(--success)' }}>Başarılı</span>
                      </>
                    ) : (
                      <>
                        <BsXCircle style={{ color: 'var(--danger)' }} />
                        <span style={{ color: 'var(--danger)' }}>Başarısız</span>
                      </>
                    )}
                  </div>
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>Model</div>
                  <div style={{ fontWeight: 500 }}>{getModelLabel(selectedLog.model_type)}</div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>ID: {selectedLog.model_id}</div>
                </div>
                {selectedLog.ip_address && (
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>IP Adresi</div>
                    <div style={{ fontWeight: 500 }}>{selectedLog.ip_address}</div>
                  </div>
                )}
              </div>
            </div>

            {/* Açıklama */}
            {selectedLog.description && (
              <div>
                <h4 style={{ marginBottom: '1rem', fontSize: '1rem', fontWeight: 600 }}>Açıklama</h4>
                <div style={{ 
                  padding: '1rem', 
                  background: 'var(--surface-glass)', 
                  borderRadius: 'var(--radius-md)',
                  fontSize: '0.875rem',
                  color: 'var(--text-secondary)'
                }}>
                  {selectedLog.description}
                </div>
              </div>
            )}

            {/* Değişiklikler (Eski/Yeni Değerler) */}
            {(selectedLog.old_values || selectedLog.new_values) && (
              <div>
                <h4 style={{ marginBottom: '1rem', fontSize: '1rem', fontWeight: 600 }}>Değişiklikler</h4>
                <div style={{ 
                  display: 'grid', 
                  gridTemplateColumns: selectedLog.old_values && selectedLog.new_values ? '1fr 1fr' : '1fr',
                  gap: '1rem'
                }}>
                  {selectedLog.old_values && (
                    <div>
                      <div style={{ 
                        fontSize: '0.75rem', 
                        color: 'var(--text-tertiary)', 
                        marginBottom: '0.5rem',
                        fontWeight: 600
                      }}>
                        Eski Değerler
                      </div>
                      <div style={{ 
                        padding: '1rem', 
                        background: 'var(--danger-soft)', 
                        borderRadius: 'var(--radius-md)',
                        border: '1px solid var(--danger)',
                        maxHeight: '400px',
                        overflowY: 'auto'
                      }}>
                        <pre style={{ 
                          margin: 0, 
                          fontSize: '0.8125rem', 
                          color: 'var(--text-primary)',
                          whiteSpace: 'pre-wrap',
                          wordBreak: 'break-word'
                        }}>
                          {JSON.stringify(selectedLog.old_values, null, 2)}
                        </pre>
                      </div>
                    </div>
                  )}
                  {selectedLog.new_values && (
                    <div>
                      <div style={{ 
                        fontSize: '0.75rem', 
                        color: 'var(--text-tertiary)', 
                        marginBottom: '0.5rem',
                        fontWeight: 600
                      }}>
                        Yeni Değerler
                      </div>
                      <div style={{ 
                        padding: '1rem', 
                        background: 'var(--success-soft)', 
                        borderRadius: 'var(--radius-md)',
                        border: '1px solid var(--success)',
                        maxHeight: '400px',
                        overflowY: 'auto'
                      }}>
                        <pre style={{ 
                          margin: 0, 
                          fontSize: '0.8125rem', 
                          color: 'var(--text-primary)',
                          whiteSpace: 'pre-wrap',
                          wordBreak: 'break-word'
                        }}>
                          {JSON.stringify(selectedLog.new_values, null, 2)}
                        </pre>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Teknik Detaylar */}
            {(selectedLog.url || selectedLog.method || selectedLog.user_agent || selectedLog.error_message) && (
              <div>
                <h4 style={{ marginBottom: '1rem', fontSize: '1rem', fontWeight: 600 }}>Teknik Detaylar</h4>
                <div style={{ 
                  padding: '1rem', 
                  background: 'var(--surface-glass)', 
                  borderRadius: 'var(--radius-md)',
                  fontSize: '0.875rem'
                }}>
                  {selectedLog.url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <span style={{ color: 'var(--text-tertiary)' }}>URL: </span>
                      <span style={{ color: 'var(--text-primary)' }}>{selectedLog.url}</span>
                    </div>
                  )}
                  {selectedLog.method && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <span style={{ color: 'var(--text-tertiary)' }}>Method: </span>
                      <span className="badge badge-secondary">{selectedLog.method}</span>
                    </div>
                  )}
                  {selectedLog.user_agent && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>User Agent:</div>
                      <div style={{ color: 'var(--text-primary)', fontSize: '0.75rem' }}>{selectedLog.user_agent}</div>
                    </div>
                  )}
                  {selectedLog.error_message && (
                    <div style={{ 
                      marginTop: '0.5rem', 
                      padding: '0.75rem', 
                      background: 'var(--danger-soft)', 
                      borderRadius: 'var(--radius-sm)',
                      color: 'var(--danger)',
                      fontSize: '0.8125rem'
                    }}>
                      <strong>Hata:</strong> {selectedLog.error_message}
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        ) : null}
      </Modal>
    </div>
  );
};

export default ActivityLogsPage;

