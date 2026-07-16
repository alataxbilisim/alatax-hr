import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { leavesApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import { usePermission } from '@shared/hooks/usePermission';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, Modal } from '../../components/ui';
import LeaveRequestForm from '../../components/leaves/LeaveRequestForm';
import LeaveTypeForm from '../../components/leaves/LeaveTypeForm';
import LeaveBalancesTab from '../../components/leaves/LeaveBalancesTab';
import LeaveCalendarTab from '../../components/leaves/LeaveCalendarTab';
import HolidaysTab from '../../components/leaves/HolidaysTab';
import AccrualPoliciesTab from '../../components/leaves/AccrualPoliciesTab';
import LeaveReportsTab from '../../components/leaves/LeaveReportsTab';
import {
  BsPlus,
  BsCheck,
  BsX,
  BsCalendarCheck,
  BsPencil,
  BsTrash,
  BsEye,
  BsFilter,
  BsSlashCircleFill,
} from 'react-icons/bs';

interface LeaveType {
  id: number;
  name: string;
  code?: string;
  description?: string;
  max_days: number;
  default_days: number;
  is_paid: boolean;
  requires_approval: boolean;
  requires_document: boolean;
  gender_restriction: 'all' | 'male' | 'female';
  max_days_at_once?: number;
  min_days_notice: number;
  is_active: boolean;
}

interface ApprovalStepRecord {
  id: number;
  status: string;
  step_order: number;
  comment?: string | null;
  step?: {
    id: number;
    name: string;
    parallel_group?: number | null;
    completion_policy?: string | null;
  } | null;
  approver?: { id: number; name: string } | null;
}

interface LeaveRequest {
  id: number;
  user: { id: number; name: string };
  leave_type: { id: number; name: string };
  start_date: string;
  end_date: string;
  total_days: number;
  reason: string;
  status: 'pending' | 'approved' | 'rejected' | 'cancelled';
  document_path?: string;
  document_name?: string;
  approved_by?: { id: number; name: string };
  rejected_by?: { id: number; name: string };
  rejection_reason?: string;
  created_at: string;
  approval_records?: ApprovalStepRecord[];
}

type TabType = 'requests' | 'types' | 'balances' | 'calendar' | 'holidays' | 'policies' | 'reports';

const LeavesPage: React.FC = () => {
  const { t } = useTranslation('common');
  const location = useLocation();
  const navigate = useNavigate();
  const { canDelete, hasPermission, isAdmin } = usePermission();
  const canCancelPending = canDelete('leaves', 'requests') || isAdmin();
  const canCancelApproved =
    hasPermission('leaves', 'requests', 'cancel') || isAdmin();

  // URL'e göre aktif tab (effect yok — pathname'den türet)
  const activeTab: TabType = useMemo(() => {
    const path = location.pathname;
    if (path.includes('/leaves/types')) return 'types';
    if (path.includes('/leaves/balances')) return 'balances';
    if (path.includes('/leaves/calendar')) return 'calendar';
    if (path.includes('/leaves/holidays')) return 'holidays';
    if (path.includes('/leaves/policies')) return 'policies';
    if (path.includes('/leaves/reports')) return 'reports';
    return 'requests';
  }, [location.pathname]);

  // Requests state
  const [requests, setRequests] = useState<LeaveRequest[]>([]);
  const [requestsLoading, setRequestsLoading] = useState(true);
  const [requestPage, setRequestPage] = useState(1);
  const [requestTotalPages, setRequestTotalPages] = useState(1);
  const [requestFormOpen, setRequestFormOpen] = useState(false);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [statusOptions, setStatusOptions] = useState<LookupItem[]>([]);
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedRequest, setSelectedRequest] = useState<LeaveRequest | null>(null);

  // Types state
  const [types, setTypes] = useState<LeaveType[]>([]);
  const [typesLoading, setTypesLoading] = useState(true);
  const [typeFormOpen, setTypeFormOpen] = useState(false);
  const [selectedType, setSelectedType] = useState<LeaveType | undefined>();
  const [deleteTypeDialogOpen, setDeleteTypeDialogOpen] = useState(false);
  const [typeToDelete, setTypeToDelete] = useState<LeaveType | null>(null);
  const [deleteTypeLoading, setDeleteTypeLoading] = useState(false);

  // Action states
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  const openRequestDetail = async (request: LeaveRequest) => {
    setSelectedRequest(request);
    setDetailModalOpen(true);
    try {
      const response = await leavesApi.requests.get(request.id);
      const data = (response.data as { data?: LeaveRequest }).data ?? (response.data as LeaveRequest);
      if (data?.id) {
        setSelectedRequest(data);
      }
    } catch {
      // Liste satırı yeterli; detay zenginleştirmesi opsiyonel
    }
  };

  // Tab değişince URL'i güncelle (activeTab pathname'den türetilir)
  const handleTabChange = (tab: TabType) => {
    const paths: Record<TabType, string> = {
      requests: '/leaves',
      types: '/leaves/types',
      balances: '/leaves/balances',
      calendar: '/leaves/calendar',
      holidays: '/leaves/holidays',
      policies: '/leaves/policies',
      reports: '/leaves/reports',
    };
    navigate(paths[tab]);
  };

  const loadRequests = useCallback(async () => {
    try {
      setRequestsLoading(true);
      const params: Record<string, unknown> = { page: requestPage, per_page: 15 };
      if (statusFilter) params.status = statusFilter;

      const response = await leavesApi.requests.list(params);
      const data = response.data.data;

      if (Array.isArray(data)) {
        setRequests(data);
        setRequestTotalPages(1);
      } else if (data?.data) {
        setRequests(data.data);
        setRequestTotalPages(data.meta?.last_page || data.last_page || 1);
      }
    } catch {
      toast.error('İzin talepleri yüklenemedi');
    } finally {
      setRequestsLoading(false);
    }
  }, [requestPage, statusFilter]);

  const loadTypes = useCallback(async () => {
    try {
      setTypesLoading(true);
      const response = await leavesApi.types.list();
      const data = response.data.data;
      setTypes(Array.isArray(data) ? data : data?.data || []);
    } catch {
      toast.error('İzin türleri yüklenemedi');
    } finally {
      setTypesLoading(false);
    }
  }, []);

  const loadStatusLookups = useCallback(async () => {
    try {
      const response = await lookupsApi.forType('leave_request_status');
      setStatusOptions(response.data.data ?? []);
    } catch {
      console.error('İzin durum lookup yüklenemedi');
    }
  }, []);

  useEffect(() => {
    loadStatusLookups();
  }, [loadStatusLookups]);

  useEffect(() => {
    if (activeTab === 'requests') {
      loadRequests();
    } else if (activeTab === 'types') {
      loadTypes();
    }
  }, [activeTab, loadRequests, loadTypes]);

  const handleApprove = async (request: LeaveRequest) => {
    setActionLoading(request.id);
    try {
      await leavesApi.requests.approve(request.id);
      toast.success('İzin talebi onaylandı');
      loadRequests();
    } catch {
      toast.error('İşlem başarısız');
    } finally {
      setActionLoading(null);
    }
  };

  const handleReject = async (request: LeaveRequest) => {
    const reason = prompt('Red nedeni:');
    if (!reason) return;

    setActionLoading(request.id);
    try {
      await leavesApi.requests.reject(request.id, reason);
      toast.success('İzin talebi reddedildi');
      loadRequests();
    } catch {
      toast.error('İşlem başarısız');
    } finally {
      setActionLoading(null);
    }
  };

  const handleCancel = async (request: LeaveRequest) => {
    if (!window.confirm(t('leaves.cancelConfirm'))) return;

    setActionLoading(request.id);
    try {
      await leavesApi.requests.cancel(request.id);
      toast.success(t('leaves.cancelSuccess'));
      loadRequests();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('leaves.cancelFailed')));
    } finally {
      setActionLoading(null);
    }
  };

  const handleDeleteType = (type: LeaveType) => {
    setTypeToDelete(type);
    setDeleteTypeDialogOpen(true);
  };

  const confirmDeleteType = async () => {
    if (!typeToDelete) return;

    setDeleteTypeLoading(true);
    try {
      await leavesApi.types.delete(typeToDelete.id);
      toast.success('İzin türü silindi');
      setDeleteTypeDialogOpen(false);
      setTypeToDelete(null);
      loadTypes();
    } catch {
      toast.error('İzin türü silinemedi');
    } finally {
      setDeleteTypeLoading(false);
    }
  };

  const getStatusBadge = (status: string) => {
    const classMap: Record<string, string> = {
      pending: 'badge-warning',
      approved: 'badge-success',
      rejected: 'badge-danger',
      cancelled: 'badge-secondary',
    };
    const label = statusOptions.find((o) => o.value === status)?.label || status;
    const className = classMap[status] || 'badge-secondary';
    return <span className={`badge ${className}`}>{label}</span>;
  };

  const requestColumns = [
    {
      key: 'user',
      title: 'Kullanıcı',
      render: (r: LeaveRequest) => (
        <div>
          <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{r.user?.name || '-'}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{r.leave_type?.name || '-'}</div>
        </div>
      ),
    },
    {
      key: 'dates',
      title: 'Tarih',
      render: (r: LeaveRequest) => (
        <div style={{ fontSize: '0.8125rem' }}>
          <div>{new Date(r.start_date).toLocaleDateString('tr-TR')}</div>
          <div style={{ color: 'var(--text-tertiary)' }}>→ {new Date(r.end_date).toLocaleDateString('tr-TR')}</div>
        </div>
      ),
    },
    {
      key: 'days',
      title: 'Gün',
      width: '60px',
      align: 'center' as const,
      render: (r: LeaveRequest) => (
        <span className="badge badge-primary">{r.total_days}</span>
      ),
    },
    {
      key: 'status',
      title: 'Durum',
      width: '100px',
      render: (r: LeaveRequest) => getStatusBadge(r.status),
    },
    {
      key: 'actions',
      title: 'İşlemler',
      width: '140px',
      align: 'right' as const,
      render: (r: LeaveRequest) => (
        <div className="table-actions">
          {r.status === 'pending' && (
            <>
              <button
                type="button"
                className="btn btn-ghost btn-icon"
                onClick={() => handleApprove(r)}
                disabled={actionLoading === r.id}
                title="Onayla"
                style={{ color: 'var(--success)' }}
              >
                <BsCheck />
              </button>
              <button
                type="button"
                className="btn btn-ghost btn-icon"
                onClick={() => handleReject(r)}
                disabled={actionLoading === r.id}
                title="Reddet"
                style={{ color: 'var(--danger)' }}
              >
                <BsX />
              </button>
              {canCancelPending && (
                <button
                  type="button"
                  className="btn btn-ghost btn-icon"
                  onClick={() => void handleCancel(r)}
                  disabled={actionLoading === r.id}
                  title={t('leaves.cancelRequest')}
                  aria-label={t('leaves.cancelRequest')}
                  style={{ color: 'var(--warning)' }}
                >
                  <BsSlashCircleFill />
                </button>
              )}
            </>
          )}
          {r.status === 'approved' && canCancelApproved && (
            <button
              type="button"
              className="btn btn-ghost btn-icon"
              onClick={() => void handleCancel(r)}
              disabled={actionLoading === r.id}
              title={t('leaves.cancelRequest')}
              aria-label={t('leaves.cancelRequest')}
              style={{ color: 'var(--warning)' }}
            >
              <BsSlashCircleFill />
            </button>
          )}
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            title="Detay"
            onClick={() => {
              void openRequestDetail(r);
            }}
          >
            <BsEye />
          </button>
        </div>
      ),
    },
  ];

  const getHeaderAction = () => {
    switch (activeTab) {
      case 'requests':
        return (
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => setRequestFormOpen(true)}>
              <BsPlus />
              Yeni Talep
            </button>
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              onClick={() => navigate('/leaves/form-engine/new')}
            >
              {t('formEngine.newBeta')}
            </button>
          </div>
        );
      case 'types':
        return (
          <button type="button" className="btn btn-primary btn-sm" onClick={() => { setSelectedType(undefined); setTypeFormOpen(true); }}>
            <BsPlus />
            Yeni Tür
          </button>
        );
      default:
        return null;
    }
  };

  const renderContent = () => {
    switch (activeTab) {
      case 'requests':
        return (
          <>
            <div className="list-filter-bar">
              <BsFilter size={16} style={{ color: 'var(--text-tertiary)', flexShrink: 0 }} />
              <div style={{ minWidth: 150 }}>
                <Select
                  value={statusFilter}
                  onChange={setStatusFilter}
                  options={statusOptions.map((opt) => ({
                    value: opt.value,
                    label: opt.label,
                    color: opt.color,
                  }))}
                  allowEmpty
                  clearable
                  emptyLabel="Tüm Durumlar"
                  placeholder="Tüm Durumlar"
                  aria-label="Durum filtresi"
                />
              </div>
            </div>
            <DataTable
              columns={requestColumns}
              data={requests}
              loading={requestsLoading}
              emptyMessage="İzin talebi bulunamadı"
              emptyIcon={<BsCalendarCheck size={32} />}
              currentPage={requestPage}
              totalPages={requestTotalPages}
              onPageChange={setRequestPage}
            />
          </>
        );

      case 'types':
        return (
          <>
            {typesLoading ? (
              <div className="page-loading">
                <div className="loading-spinner" />
              </div>
            ) : types.length === 0 ? (
              <div className="card">
                <div className="card-body empty-state">
                  <BsCalendarCheck size={48} style={{ color: 'var(--text-muted)' }} />
                  <h3 className="empty-state-title mt-3">İzin Türü Bulunamadı</h3>
                  <p className="empty-state-text">
                    Henüz tanımlı izin türü yok. İlk izin türünü oluşturun.
                  </p>
                  <button
                    className="btn btn-primary mt-2"
                    onClick={() => {
                      setSelectedType(undefined);
                      setTypeFormOpen(true);
                    }}
                  >
                    <BsPlus /> İzin Türü Oluştur
                  </button>
                </div>
              </div>
            ) : (
              <div className="card">
                <div className="table-container">
                  <table className="table">
                    <thead>
                      <tr>
                        <th>İzin Türü</th>
                        <th>Kod</th>
                        <th>Varsayılan Gün</th>
                        <th>Ücretli</th>
                        <th>Belge Gerekli</th>
                        <th>Durum</th>
                        <th style={{ textAlign: 'right' }}>İşlemler</th>
                      </tr>
                    </thead>
                    <tbody>
                      {types.map((type) => (
                        <tr key={type.id}>
                          <td>
                            <div>
                              <span style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{type.name}</span>
                              {type.description && (
                                <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{type.description}</div>
                              )}
                            </div>
                          </td>
                          <td>
                            {type.code && <span className="badge badge-secondary">{type.code}</span>}
                          </td>
                          <td>
                            <span className="badge badge-primary">{type.default_days} gün</span>
                          </td>
                          <td>
                            <span className={`badge ${type.is_paid ? 'badge-success' : 'badge-secondary'}`}>
                              {type.is_paid ? 'Evet' : 'Hayır'}
                            </span>
                          </td>
                          <td>
                            <span className={`badge ${type.requires_document ? 'badge-warning' : 'badge-secondary'}`}>
                              {type.requires_document ? 'Evet' : 'Hayır'}
                            </span>
                          </td>
                          <td>
                            <span className={`badge ${type.is_active ? 'badge-success' : 'badge-secondary'}`}>
                              {type.is_active ? 'Aktif' : 'Pasif'}
                            </span>
                          </td>
                          <td style={{ textAlign: 'right' }}>
                            <div style={{ display: 'flex', gap: '0.25rem', justifyContent: 'flex-end' }}>
                              <button
                                className="btn btn-ghost btn-icon btn-sm"
                                onClick={() => {
                                  setSelectedType(type);
                                  setTypeFormOpen(true);
                                }}
                                title="Düzenle"
                              >
                                <BsPencil />
                              </button>
                              <button
                                className="btn btn-ghost btn-icon btn-sm"
                                onClick={() => handleDeleteType(type)}
                                title="Sil"
                                style={{ color: 'var(--danger)' }}
                              >
                                <BsTrash />
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </>
        );

      case 'balances':
        return <LeaveBalancesTab />;

      case 'calendar':
        return <LeaveCalendarTab />;

      case 'holidays':
        return <HolidaysTab />;

      case 'policies':
        return <AccrualPoliciesTab />;

      case 'reports':
        return <LeaveReportsTab />;

      default:
        return null;
    }
  };

  return (
    <div className="animate-fade-in list-page">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">İzinler</h1>
        </div>
        <div className="page-header-actions">
          {getHeaderAction()}
        </div>
      </div>

      <div className="tabs">
        <button
          type="button"
          className={`tab ${activeTab === 'requests' ? 'active' : ''}`}
          onClick={() => handleTabChange('requests')}
        >
          İzin Talepleri
        </button>
        <button
          type="button"
          className={`tab ${activeTab === 'types' ? 'active' : ''}`}
          onClick={() => handleTabChange('types')}
        >
          İzin Türleri
        </button>
        <button
          type="button"
          className={`tab ${activeTab === 'balances' ? 'active' : ''}`}
          onClick={() => handleTabChange('balances')}
        >
          Bakiyeler
        </button>
        <button
          type="button"
          className={`tab ${activeTab === 'calendar' ? 'active' : ''}`}
          onClick={() => handleTabChange('calendar')}
        >
          Takvim
        </button>
        <button
          type="button"
          className={`tab ${activeTab === 'holidays' ? 'active' : ''}`}
          onClick={() => handleTabChange('holidays')}
        >
          Tatiller
        </button>
        <button
          type="button"
          className={`tab ${activeTab === 'policies' ? 'active' : ''}`}
          onClick={() => handleTabChange('policies')}
        >
          Hakediş Politikaları
        </button>
        <button
          type="button"
          className={`tab ${activeTab === 'reports' ? 'active' : ''}`}
          onClick={() => handleTabChange('reports')}
        >
          Raporlar
        </button>
      </div>

      {renderContent()}

      {/* Modals */}
      <LeaveRequestForm
        isOpen={requestFormOpen}
        onClose={() => setRequestFormOpen(false)}
        onSuccess={loadRequests}
      />

      <LeaveTypeForm
        isOpen={typeFormOpen}
        onClose={() => setTypeFormOpen(false)}
        onSuccess={loadTypes}
        leaveType={selectedType}
      />

      <ConfirmDialog
        isOpen={deleteTypeDialogOpen}
        onClose={() => setDeleteTypeDialogOpen(false)}
        onConfirm={confirmDeleteType}
        title="İzin Türünü Sil"
        message={`"${typeToDelete?.name}" izin türünü silmek istediğinize emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleteTypeLoading}
      />

      {/* Request Detail Modal */}
      <Modal
        isOpen={detailModalOpen}
        onClose={() => setDetailModalOpen(false)}
        title="İzin Talebi Detayı"
        size="md"
      >
        {selectedRequest && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            <div className="row">
              <div className="col-6">
                <label className="form-label text-muted">Personel</label>
                <p style={{ fontWeight: 500 }}>{selectedRequest.user?.name}</p>
              </div>
              <div className="col-6">
                <label className="form-label text-muted">İzin Türü</label>
                <p style={{ fontWeight: 500 }}>{selectedRequest.leave_type?.name}</p>
              </div>
            </div>
            <div className="row">
              <div className="col-6">
                <label className="form-label text-muted">Başlangıç</label>
                <p>{new Date(selectedRequest.start_date).toLocaleDateString('tr-TR')}</p>
              </div>
              <div className="col-6">
                <label className="form-label text-muted">Bitiş</label>
                <p>{new Date(selectedRequest.end_date).toLocaleDateString('tr-TR')}</p>
              </div>
            </div>
            <div className="row">
              <div className="col-6">
                <label className="form-label text-muted">Toplam Gün</label>
                <p><span className="badge badge-primary">{selectedRequest.total_days} gün</span></p>
              </div>
              <div className="col-6">
                <label className="form-label text-muted">Durum</label>
                <p>{getStatusBadge(selectedRequest.status)}</p>
              </div>
            </div>
            {selectedRequest.reason && (
              <div>
                <label className="form-label text-muted">Açıklama</label>
                <p>{selectedRequest.reason}</p>
              </div>
            )}
            {selectedRequest.document_name && (
              <div>
                <label className="form-label text-muted">Belge</label>
                <p>{selectedRequest.document_name}</p>
              </div>
            )}
            {selectedRequest.rejection_reason && (
              <div>
                <label className="form-label text-muted">{t('leaves.rejectionReason')}</label>
                <p style={{ color: 'var(--danger)' }}>{selectedRequest.rejection_reason}</p>
              </div>
            )}
            <div>
              <label className="form-label text-muted">{t('leaves.approvalSteps')}</label>
              {(selectedRequest.approval_records?.length ?? 0) === 0 ? (
                <p style={{ color: 'var(--text-tertiary)', fontSize: 'var(--fs-sm)' }}>
                  {t('leaves.noApprovalSteps')}
                </p>
              ) : (
                <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                  {selectedRequest.approval_records?.map((rec) => (
                    <li
                      key={rec.id}
                      style={{
                        border: '1px solid var(--border-color)',
                        borderRadius: 'var(--radius-sm)',
                        padding: '0.5rem 0.75rem',
                      }}
                    >
                      <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.5rem', flexWrap: 'wrap' }}>
                        <span style={{ fontWeight: 500 }}>
                          #{rec.step_order} {rec.step?.name ?? '—'}
                        </span>
                        <span className="badge badge-secondary">{rec.status}</span>
                      </div>
                      {rec.step?.parallel_group != null && (
                        <div style={{ fontSize: 'var(--fs-sm)', color: 'var(--text-secondary)', marginTop: 4 }}>
                          {t('leaves.parallelGroup', { group: rec.step.parallel_group })}
                          {rec.step.completion_policy ? ` · ${rec.step.completion_policy}` : ''}
                        </div>
                      )}
                      <div style={{ fontSize: 'var(--fs-sm)', color: 'var(--text-tertiary)', marginTop: 2 }}>
                        {t('leaves.approver')}: {rec.approver?.name ?? '—'}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default LeavesPage;
