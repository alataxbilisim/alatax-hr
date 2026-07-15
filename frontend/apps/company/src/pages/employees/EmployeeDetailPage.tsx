import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  BsArrowLeft,
  BsPencil,
  BsTrash,
  BsPersonBadge,
  BsBuilding,
  BsBriefcase,
  BsFileEarmark,
  BsCalendarCheck,
  BsBook,
  BsLaptop,
  BsClockHistory,
  BsThreeDotsVertical,
  BsKey,
  BsKeyFill,
  BsListCheck,
} from 'react-icons/bs';
import { employeesApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { useTranslation } from '@shared/i18n';
import { ConfirmDialog, Modal } from '../../components/ui';
import {
  GeneralTab,
  PersonalTab,
  WorkTab,
  DocumentsTab,
  LeavesTab,
  TrainingTab,
  AssetsTab,
  HistoryTab,
  CustomFieldsTab,
} from '../../components/employees/tabs';
import type {
  EmployeeLeaveData,
  EmployeeTrainingData,
  EmployeeAssetData,
  ActivityLog,
} from '../../components/employees/tabs';
import type { CustomFieldValue } from '@shared/types/modules';

interface EmployeeDocumentRow {
  id: number;
  title: string;
  description?: string;
  category: string;
  file_name: string;
  file_type?: string;
  file_size?: number;
  issue_date?: string;
  expiry_date?: string;
  is_expired: boolean;
  status: string;
  created_at: string;
  uploaded_by?: { id: number; name: string };
}

interface Employee {
  id: number;
  employee_code: string;
  user_id?: number | null;
  position?: string;
  title?: string;
  status: string;
  hire_date?: string;
  birth_date?: string;
  national_id?: string;
  gender?: string;
  marital_status?: string;
  blood_type?: string;
  education_level?: string;
  personal_email?: string;
  personal_phone?: string;
  address?: string;
  city?: string;
  district?: string;
  postal_code?: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
  emergency_contact_relation?: string;
  contract_start_date?: string;
  contract_end_date?: string;
  contract_type?: string;
  work_type?: string;
  gross_salary?: number;
  net_salary?: number;
  currency?: string;
  bank_name?: string;
  iban?: string;
  sgk_number?: string;
  sgk_start_date?: string;
  notes?: string;
  custom_fields?: Record<string, CustomFieldValue>;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  department?: {
    id: number;
    name: string;
  };
  manager?: {
    id: number;
    user?: {
      name: string;
    };
  };
  subordinates?: Array<{
    id: number;
    user?: { name: string };
    position?: string;
  }>;
  documents?: EmployeeDocumentRow[];
}

interface TabItem {
  id: string;
  label: string;
  icon: React.ReactNode;
}

const EmployeeDetailPage: React.FC = () => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const [employee, setEmployee] = useState<Employee | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('general');
  
  // İlişkili veriler
  const [leaveData, setLeaveData] = useState<EmployeeLeaveData | null>(null);
  const [trainingData, setTrainingData] = useState<EmployeeTrainingData | null>(null);
  const [assetData, setAssetData] = useState<EmployeeAssetData | null>(null);
  const [activityLog, setActivityLog] = useState<ActivityLog[]>([]);
  
  // Modal durumları
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [portalAccessModalOpen, setPortalAccessModalOpen] = useState(false);
  const [revokeAccessDialogOpen, setRevokeAccessDialogOpen] = useState(false);
  const [actionsMenuOpen, setActionsMenuOpen] = useState(false);

  const tabs: TabItem[] = [
    { id: 'general', label: 'Genel', icon: <BsPersonBadge /> },
    { id: 'personal', label: 'Kişisel', icon: <BsBuilding /> },
    { id: 'work', label: 'İş Bilgileri', icon: <BsBriefcase /> },
    { id: 'custom', label: t('customFields.tab'), icon: <BsListCheck /> },
    { id: 'documents', label: 'Belgeler', icon: <BsFileEarmark /> },
    { id: 'leaves', label: 'İzinler', icon: <BsCalendarCheck /> },
    { id: 'trainings', label: 'Eğitimler', icon: <BsBook /> },
    { id: 'assets', label: 'Zimmetler', icon: <BsLaptop /> },
    { id: 'history', label: 'Geçmiş', icon: <BsClockHistory /> },
  ];
  
  // Portal erişimi form
  const [portalForm, setPortalForm] = useState({
    email: '',
    name: '',
    access_mode: 'invite' as 'invite' | 'set_password',
    password: '',
  });
  const [portalLoading, setPortalLoading] = useState(false);

  const loadEmployee = useCallback(async () => {
    try {
      setLoading(true);
      const response = await employeesApi.getById(Number(id));
      const data = response.data.data;
      
      setEmployee(data.employee);
      setLeaveData(data.leaves);
      setTrainingData(data.trainings);
      setAssetData(data.assets);
      setActivityLog(data.activity_log || []);
    } catch {
      toast.error('Personel bilgileri yüklenemedi');
      navigate('/employees');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  useEffect(() => {
    if (id) {
      loadEmployee();
    }
  }, [id, loadEmployee]);

  

  const handleDelete = async () => {
    try {
      await employeesApi.delete(Number(id));
      toast.success('Personel silindi');
      navigate('/employees');
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Silme işlemi başarısız'));
    }
  };

  const handleCreatePortalAccess = async () => {
    if (!portalForm.email || !portalForm.name) {
      toast.error('Lütfen tüm alanları doldurun');
      return;
    }
    if (portalForm.access_mode === 'set_password' && !portalForm.password) {
      toast.error('Şifre gerekli');
      return;
    }

    try {
      setPortalLoading(true);
      const payload: {
        email: string;
        name: string;
        access_mode: 'invite' | 'set_password';
        password?: string;
      } = {
        email: portalForm.email,
        name: portalForm.name,
        access_mode: portalForm.access_mode,
      };
      if (portalForm.access_mode === 'set_password') {
        payload.password = portalForm.password;
      }
      await employeesApi.createPortalAccess(Number(id), payload);
      toast.success(
        portalForm.access_mode === 'invite'
          ? 'Portal daveti gönderildi'
          : 'Portal erişimi oluşturuldu'
      );
      setPortalAccessModalOpen(false);
      setPortalForm({ email: '', name: '', access_mode: 'invite', password: '' });
      loadEmployee();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Portal erişimi oluşturulamadı'));
    } finally {
      setPortalLoading(false);
    }
  };

  const handleRevokePortalAccess = async () => {
    try {
      await employeesApi.revokePortalAccess(Number(id));
      toast.success('Portal erişimi kaldırıldı');
      setRevokeAccessDialogOpen(false);
      loadEmployee();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Portal erişimi kaldırılamadı'));
    }
  };

  const getStatusBadge = (status: string) => {
    const statusMap: Record<string, { label: string; className: string }> = {
      active: { label: 'Aktif', className: 'badge-success' },
      on_leave: { label: 'İzinli', className: 'badge-warning' },
      suspended: { label: 'Askıda', className: 'badge-danger' },
      terminated: { label: 'İşten Çıkmış', className: 'badge-secondary' },
    };
    const statusInfo = statusMap[status] || { label: status, className: 'badge-secondary' };
    return <span className={`badge ${statusInfo.className}`}>{statusInfo.label}</span>;
  };

  if (loading) {
    return (
      <div className="animate-fade-in">
        <div className="page-header">
          <div className="page-header-content">
            <h1 className="page-title">Personel Detayı</h1>
          </div>
        </div>
        <div className="card">
          <div className="card-body">
            <div className="text-center py-4">
              <div className="spinner-border" role="status">
                <span className="visually-hidden">Yükleniyor...</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!employee) {
    return null;
  }

  return (
    <div className="animate-fade-in detail-page">
      <div className="detail-identity">
        <button
          type="button"
          className="btn btn-ghost btn-icon"
          onClick={() => navigate('/employees')}
          title="Personel Listesi"
          aria-label="Personel Listesi"
        >
          <BsArrowLeft />
        </button>
        <div className="detail-identity-avatar" aria-hidden>
          {(employee.user?.name || '?').charAt(0).toUpperCase()}
        </div>
        <div className="detail-identity-main">
          <div className="detail-identity-title-row">
            <h1 className="page-title">{employee.user?.name || 'İsimsiz Personel'}</h1>
            {getStatusBadge(employee.status)}
          </div>
          <div className="detail-identity-meta">
            <span className="badge badge-secondary">{employee.employee_code}</span>
            {employee.department && <span>{employee.department.name}</span>}
            {employee.position && <span>{employee.position}</span>}
            {employee.title && <span>{employee.title}</span>}
          </div>
        </div>
        <div className="detail-identity-actions">
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => navigate(`/employees/${id}/edit`)}
          >
            <BsPencil /> Düzenle
          </button>
          <div style={{ position: 'relative' }}>
            <button
              type="button"
              className="btn btn-secondary btn-icon"
              onClick={() => setActionsMenuOpen(!actionsMenuOpen)}
              aria-label="Diğer işlemler"
            >
              <BsThreeDotsVertical />
            </button>
            {actionsMenuOpen && (
              <>
                <div
                  style={{ position: 'fixed', inset: 0, zIndex: 10 }}
                  onClick={() => setActionsMenuOpen(false)}
                />
                <div
                  style={{
                    position: 'absolute',
                    top: '100%',
                    right: 0,
                    marginTop: 'var(--sp-1)',
                    background: 'var(--bg-elevated)',
                    border: '1px solid var(--border-primary)',
                    borderRadius: 'var(--radius-md)',
                    boxShadow: 'var(--shadow-lg)',
                    minWidth: 180,
                    zIndex: 20,
                  }}
                >
                  {employee.user ? (
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      style={{ width: '100%', justifyContent: 'flex-start', borderRadius: 0 }}
                      onClick={() => {
                        setActionsMenuOpen(false);
                        setRevokeAccessDialogOpen(true);
                      }}
                    >
                      <BsKey style={{ color: 'var(--warning)' }} /> Portal Erişimini Kaldır
                    </button>
                  ) : (
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      style={{ width: '100%', justifyContent: 'flex-start', borderRadius: 0 }}
                      onClick={() => {
                        setActionsMenuOpen(false);
                        setPortalAccessModalOpen(true);
                        setPortalForm({
                          email: employee.personal_email || '',
                          name: employee.user?.name || '',
                          access_mode: 'invite',
                          password: '',
                        });
                      }}
                    >
                      <BsKeyFill style={{ color: 'var(--success)' }} /> Portal Erişimi Ver
                    </button>
                  )}
                  <button
                    type="button"
                    className="btn btn-ghost btn-sm"
                    style={{ width: '100%', justifyContent: 'flex-start', borderRadius: 0, color: 'var(--danger)' }}
                    onClick={() => {
                      setActionsMenuOpen(false);
                      setDeleteDialogOpen(true);
                    }}
                  >
                    <BsTrash /> Sil
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      </div>

      <div className="tabs">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            className={`tab ${activeTab === tab.id ? 'active' : ''}`}
            onClick={() => setActiveTab(tab.id)}
          >
            {tab.icon}
            {tab.label}
          </button>
        ))}
      </div>

      {/* Tab Content */}
      <div>
        {activeTab === 'general' && (
          <GeneralTab
            employee={employee}
            onCreatePortalAccess={() => {
              setPortalAccessModalOpen(true);
              setPortalForm({
                email: employee.personal_email || '',
                name: employee.user?.name || '',
                access_mode: 'invite',
                password: '',
              });
            }}
            onRevokePortalAccess={() => setRevokeAccessDialogOpen(true)}
          />
        )}

        {activeTab === 'personal' && <PersonalTab employee={employee} />}

        {activeTab === 'work' && <WorkTab employee={employee} />}

        {activeTab === 'custom' && (
          <CustomFieldsTab values={employee.custom_fields || {}} />
        )}

        {activeTab === 'documents' && (
          <DocumentsTab
            employeeId={Number(id)}
            documents={employee.documents || []}
            onRefresh={loadEmployee}
          />
        )}

        {activeTab === 'leaves' && (
          <LeavesTab
            balances={leaveData?.balances || []}
            requests={leaveData?.requests || []}
          />
        )}

        {activeTab === 'trainings' && (
          <TrainingTab
            participations={trainingData?.participations || []}
            certificates={trainingData?.certificates || []}
          />
        )}

        {activeTab === 'assets' && (
          <AssetsTab
            active={assetData?.active || []}
            history={assetData?.history || []}
          />
        )}

        {activeTab === 'history' && <HistoryTab activities={activityLog} />}
      </div>

      {/* Silme Dialog */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={handleDelete}
        title="Personeli Sil"
        message="Bu personeli silmek istediğinizden emin misiniz? Bu işlem geri alınamaz."
        confirmText="Sil"
        variant="danger"
      />

      {/* Portal Erişimi Kaldırma Dialog */}
      <ConfirmDialog
        isOpen={revokeAccessDialogOpen}
        onClose={() => setRevokeAccessDialogOpen(false)}
        onConfirm={handleRevokePortalAccess}
        title="Portal Erişimini Kaldır"
        message="Bu personelin portal erişimini kaldırmak istediğinizden emin misiniz?"
        confirmText="Erişimi Kaldır"
        variant="danger"
      />

      {/* Portal Erişimi Oluşturma Modal */}
      <Modal
        isOpen={portalAccessModalOpen}
        onClose={() => setPortalAccessModalOpen(false)}
        title="Portal Erişimi Ver"
        size="sm"
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div className="form-group">
            <label className="form-label">Ad Soyad *</label>
            <input
              type="text"
              className="form-control"
              value={portalForm.name}
              onChange={(e) => setPortalForm({ ...portalForm, name: e.target.value })}
              placeholder="Kullanıcı adı"
            />
          </div>
          <div className="form-group">
            <label className="form-label">Email *</label>
            <input
              type="email"
              className="form-control"
              value={portalForm.email}
              onChange={(e) => setPortalForm({ ...portalForm, email: e.target.value })}
              placeholder="Email adresi"
            />
          </div>
          <div className="form-group">
            <label className="form-label">Erişim yöntemi</label>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.5rem', cursor: 'pointer' }}>
              <input
                type="radio"
                checked={portalForm.access_mode === 'invite'}
                onChange={() => setPortalForm({ ...portalForm, access_mode: 'invite', password: '' })}
              />
              <span>Davet e-postası gönder</span>
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="radio"
                checked={portalForm.access_mode === 'set_password'}
                onChange={() => setPortalForm({ ...portalForm, access_mode: 'set_password' })}
              />
              <span>Şifreyi şimdi belirle</span>
            </label>
          </div>
          {portalForm.access_mode === 'set_password' && (
            <div className="form-group">
              <label className="form-label">Şifre *</label>
              <input
                type="password"
                className="form-control"
                value={portalForm.password}
                onChange={(e) => setPortalForm({ ...portalForm, password: e.target.value })}
                autoComplete="new-password"
                placeholder="En az 8 karakter"
              />
            </div>
          )}
          <p style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
            {portalForm.access_mode === 'invite'
              ? 'Personele şifre belirleme bağlantısı e-posta ile gönderilir.'
              : 'Belirlediğiniz şifre ile giriş yapabilir; ilk girişte şifre değiştirmesi zorunludur.'}
          </p>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem' }}>
            <button className="btn btn-secondary" onClick={() => setPortalAccessModalOpen(false)}>
              İptal
            </button>
            <button
              className="btn btn-success"
              onClick={handleCreatePortalAccess}
              disabled={
                portalLoading ||
                !portalForm.email ||
                !portalForm.name ||
                (portalForm.access_mode === 'set_password' && !portalForm.password)
              }
            >
              {portalLoading ? 'Oluşturuluyor...' : 'Erişim Ver'}
            </button>
          </div>
        </div>
      </Modal>
    </div>
  );
};

export default EmployeeDetailPage;
