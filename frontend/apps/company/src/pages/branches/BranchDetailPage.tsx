import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { branchesApi, activityLogsApi, usersApi } from '@shared/services/api';
import { Branch, User } from '@shared/types/modules';
import toast from 'react-hot-toast';
import {
  BsArrowLeft,
  BsPencil,
  BsBuilding,
  BsPeople,
  BsGeoAlt,
  BsTelephone,
  BsEnvelope,
  BsStarFill,
} from 'react-icons/bs';
import { DataTable } from '../../components/ui';
import BranchForm from './BranchForm';

interface BranchStats {
  total_employees: number;
  active_employees: number;
  total_actions: number;
  last_activity: string | null;
}

interface ActivityLog {
  id: number;
  action: string;
  description: string | null;
  created_at: string;
  ip_address: string | null;
  is_successful: boolean;
  causer?: { id: number; name: string } | null;
}

/** Şube çalışan satırı (API User/Employee karışık alanlar dönebilir) */
interface BranchEmployeeRow {
  id: number;
  name?: string;
  email?: string;
  is_active?: boolean;
  user_id?: number;
  department?: string | { name?: string } | null;
  user?: {
    id?: number;
    name?: string;
    email?: string;
    department?: string;
    is_active?: boolean;
  } | null;
}

const actionLabels: Record<string, string> = {
  created: 'Oluşturuldu',
  updated: 'Güncellendi',
  deleted: 'Silindi',
  viewed: 'Görüntülendi',
};

const BranchDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [branch, setBranch] = useState<Branch | null>(null);
  const [stats, setStats] = useState<BranchStats | null>(null);
  const [employees, setEmployees] = useState<BranchEmployeeRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [employeesLoading, setEmployeesLoading] = useState(false);
  const [activities, setActivities] = useState<ActivityLog[]>([]);
  const [activitiesLoading, setActivitiesLoading] = useState(false);
  const [formOpen, setFormOpen] = useState(false);
  const [users, setUsers] = useState<User[]>([]);

  const loadUsers = useCallback(async () => {
    try {
      const response = await usersApi.list({ per_page: 100 });
      setUsers(response.data.data?.data || response.data.data || []);
    } catch {
      // Manager listesi opsiyonel
    }
  }, []);

  const loadBranch = useCallback(async () => {
    try {
      setLoading(true);
      const response = await branchesApi.get(Number(id));
      const branchData = response.data.data as Branch;
      setBranch(branchData);
      
      // Calculate stats
      const employeesResponse = await branchesApi.employees(Number(id), { per_page: 1 });
      const employeesData = employeesResponse.data.data?.data || employeesResponse.data.data || [];
      setStats({
        total_employees: employeesData.length || 0,
        active_employees: employeesData.filter((e: BranchEmployeeRow) => e.is_active ?? e.user?.is_active).length || 0,
        total_actions: 0,
        last_activity: null,
      });
    } catch {
      toast.error('Şube yüklenemedi');
      navigate('/branches');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  const loadEmployees = useCallback(async () => {
    try {
      setEmployeesLoading(true);
      const response = await branchesApi.employees(Number(id), { per_page: 50 });
      const employeesData = response.data.data?.data || response.data.data || [];
      setEmployees(employeesData);
    } catch {
      toast.error('Çalışanlar yüklenemedi');
    } finally {
      setEmployeesLoading(false);
    }
  }, [id]);

  const loadActivities = useCallback(async () => {
    try {
      setActivitiesLoading(true);
      const response = await activityLogsApi.list({
        subject_type: 'App\\Models\\Branch',
        subject_id: id,
        per_page: 10,
      });
      setActivities(response.data.data || []);
    } catch {
      toast.error('Aktivite logları yüklenemedi');
    } finally {
      setActivitiesLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (id) {
      loadBranch();
      loadEmployees();
      loadActivities();
      loadUsers();
    }
  }, [id, loadUsers, loadBranch, loadEmployees, loadActivities]);

  

  

  

  

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('tr-TR', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner" />
      </div>
    );
  }

  if (!branch) {
    return null;
  }

  const employeeColumns = [
    {
      key: 'name',
      title: 'Çalışan',
      render: (employee: BranchEmployeeRow) => (
        <div>
          <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{employee.name || employee.user?.name}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            {employee.email || employee.user?.email}
          </div>
        </div>
      ),
    },
    {
      key: 'department',
      title: 'Departman',
      render: (employee: BranchEmployeeRow) => {
        const dept = employee.department;
        if (typeof dept === 'string') return dept;
        if (dept && typeof dept === 'object' && 'name' in dept) return dept.name || '-';
        return employee.user?.department || '-';
      },
    },
    {
      key: 'status',
      title: 'Durum',
      width: '100px',
      render: (employee: BranchEmployeeRow) => (
        <span className={`badge ${(employee.is_active ?? employee.user?.is_active) ? 'badge-success' : 'badge-secondary'}`}>
          {(employee.is_active ?? employee.user?.is_active) ? 'Aktif' : 'Pasif'}
        </span>
      ),
    },
    {
      key: 'actions',
      title: 'İşlemler',
      width: '100px',
      align: 'right' as const,
      render: (employee: BranchEmployeeRow) => (
        <button
          className="btn btn-ghost btn-sm"
          onClick={() => navigate(`/users/${employee.user_id || employee.user?.id}`)}
        >
          Detay
        </button>
      ),
    },
  ];

  const activityColumns = [
    {
      key: 'action',
      title: 'İşlem',
      width: '120px',
      render: (log: ActivityLog) => (
        <span className={`badge ${log.is_successful ? 'badge-success' : 'badge-danger'}`}>
          {actionLabels[log.action] || log.action}
        </span>
      ),
    },
    {
      key: 'description',
      title: 'Açıklama',
      render: (log: ActivityLog) => log.description || '-',
    },
    {
      key: 'causer',
      title: 'Kullanıcı',
      width: '150px',
      render: (log: ActivityLog) => log.causer?.name || 'Sistem',
    },
    {
      key: 'created_at',
      title: 'Tarih',
      width: '150px',
      render: (log: ActivityLog) => formatDateTime(log.created_at),
    },
  ];

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-icon"
            onClick={() => navigate('/branches')}
            style={{ marginRight: '0.75rem' }}
          >
            <BsArrowLeft />
          </button>
          <div>
            <h1>
              {branch.name}
              {branch.is_headquarters && (
                <BsStarFill style={{ marginLeft: '0.5rem', color: 'var(--warning)', fontSize: '1.25rem' }} />
              )}
            </h1>
            <p>Şube detayları ve bilgileri</p>
          </div>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-primary"
            onClick={() => setFormOpen(true)}
          >
            <BsPencil style={{ marginRight: 6 }} /> Düzenle
          </button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '1.5rem' }}>
        {/* Sol Panel - Şube Bilgileri */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Şube Kartı */}
          <div className="card">
            <div className="card-body" style={{ textAlign: 'center' }}>
              <div
                style={{
                  width: 80,
                  height: 80,
                  borderRadius: 'var(--radius-md)',
                  background: 'var(--primary-soft)',
                  color: 'var(--primary)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 1rem',
                }}
              >
                <BsBuilding size={40} />
              </div>
              <h3 style={{ marginBottom: '0.5rem' }}>{branch.name}</h3>
              {branch.code && (
                <p style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem', marginBottom: '1rem' }}>
                  Kod: {branch.code}
                </p>
              )}
              <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'center' }}>
                <span className={`badge ${branch.is_active ? 'badge-success' : 'badge-secondary'}`}>
                  {branch.is_active ? 'Aktif' : 'Pasif'}
                </span>
                {branch.is_headquarters && (
                  <span className="badge badge-warning">Merkez Şube</span>
                )}
              </div>
            </div>
          </div>

          {/* İstatistikler */}
          {stats && (
            <div className="card">
              <div className="card-header">
                <h3 className="card-title">İstatistikler</h3>
              </div>
              <div className="card-body">
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <BsPeople style={{ color: 'var(--primary)' }} />
                      <span>Toplam Çalışan</span>
                    </div>
                    <strong>{stats.total_employees}</strong>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <BsPeople style={{ color: 'var(--success)' }} />
                      <span>Aktif Çalışan</span>
                    </div>
                    <strong>{stats.active_employees}</strong>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* İletişim Bilgileri */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">İletişim Bilgileri</h3>
            </div>
            <div className="card-body">
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                {branch.address && (
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}>
                    <BsGeoAlt style={{ marginTop: '0.25rem', color: 'var(--text-tertiary)' }} />
                    <div>
                      <div style={{ fontWeight: 500 }}>{branch.address}</div>
                      {(branch.city || branch.district) && (
                        <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                          {[branch.district, branch.city, branch.country].filter(Boolean).join(', ')}
                        </div>
                      )}
                      {branch.postal_code && (
                        <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                          Posta Kodu: {branch.postal_code}
                        </div>
                      )}
                    </div>
                  </div>
                )}
                {branch.phone && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <BsTelephone style={{ color: 'var(--text-tertiary)' }} />
                    <span>{branch.phone}</span>
                  </div>
                )}
                {branch.email && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <BsEnvelope style={{ color: 'var(--text-tertiary)' }} />
                    <span>{branch.email}</span>
                  </div>
                )}
                {branch.manager && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginTop: '0.5rem', paddingTop: '0.75rem', borderTop: '1px solid var(--border-color)' }}>
                    <BsPeople style={{ color: 'var(--text-tertiary)' }} />
                    <div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Şube Müdürü</div>
                      <div style={{ fontWeight: 500 }}>{branch.manager.name}</div>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Bilgiler */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Bilgiler</h3>
            </div>
            <div className="card-body">
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                    Oluşturulma
                  </div>
                  <div>{formatDateTime(branch.created_at)}</div>
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                    Son Güncelleme
                  </div>
                  <div>{formatDateTime(branch.updated_at)}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Sağ Panel - Çalışanlar ve Aktivite */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Çalışanlar */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Çalışanlar ({employees.length})</h3>
            </div>
            <div className="card-body">
              {employeesLoading ? (
                <div className="page-loading">
                  <div className="loading-spinner" />
                </div>
              ) : employees.length > 0 ? (
                <DataTable
                  data={employees}
                  columns={employeeColumns}
                />
              ) : (
                <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                  Bu şubede çalışan bulunmuyor
                </div>
              )}
            </div>
          </div>

          {/* Aktivite Logları */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Aktivite Logları</h3>
            </div>
            <div className="card-body">
              {activitiesLoading ? (
                <div className="page-loading">
                  <div className="loading-spinner" />
                </div>
              ) : activities.length > 0 ? (
                <DataTable
                  data={activities}
                  columns={activityColumns}
                />
              ) : (
                <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                  Aktivite logu bulunmuyor
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Branch Form Modal */}
      {formOpen && (
        <BranchForm
          onClose={() => setFormOpen(false)}
          onSuccess={() => {
            setFormOpen(false);
            loadBranch();
          }}
          branch={branch}
          users={users}
        />
      )}
    </div>
  );
};

export default BranchDetailPage;

